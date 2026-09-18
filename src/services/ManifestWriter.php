<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use RuntimeException;

/**
 * manifest.json 回写器
 *
 * 后台编辑工具元数据时的唯一落盘出口（docs/manifest规范.md §6）：
 *   - 默认写回 tools/{id}/manifest.json（格式化、字段顺序稳定、LF、末尾空行）
 *   - tools/ 不可写时降级写 tool_overrides 表，后台提示降级模式
 *
 * 降级覆盖（overrides）由 ToolScanner 在扫描时合并生效，保证「库里的数据」
 * 始终等于「用户最后一次编辑的结果」，而非磁盘上的陈旧文件。
 */
final class ManifestWriter
{
    /** manifest 字段输出顺序，与 docs/manifest规范.md §2 表格一致 */
    private const FIELD_ORDER = [
        'id', 'title', 'version', 'type', 'description', 'author', 'entry',
        'single_file', 'offline', 'grade_range', 'subjects', 'tags', 'family',
        'license', 'dependencies', 'screen', 'stats_enabled', 'created_at', 'updated_at',
    ];

    private string $toolsPath;

    private ?Database $db;

    public function __construct(?string $toolsPath = null, ?Database $db = null)
    {
        $this->toolsPath = $toolsPath ?? App::path('tools');
        $this->db = $db;
    }

    /**
     * 工具目录是否可写（决定走文件回写还是 DB 降级）。
     */
    public function isWritable(string $toolId): bool
    {
        if (!Security::isValidToolId($toolId)) {
            return false;
        }

        $dir = $this->toolsPath . DIRECTORY_SEPARATOR . $toolId;
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'manifest.json';

        return !is_file($file) || is_writable($file);
    }

    /**
     * 读取磁盘上的 manifest.json。
     *
     * @return array<string, mixed>|null 文件不存在或 JSON 非法时返回 null
     */
    public function read(string $toolId): ?array
    {
        if (!Security::isValidToolId($toolId)) {
            return null;
        }

        $file = $this->toolsPath . DIRECTORY_SEPARATOR . $toolId . DIRECTORY_SEPARATOR . 'manifest.json';
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * 回写 manifest。
     *
     * @param array<string, mixed> $fields 要修改的字段（增量，与磁盘现有内容合并）；
     *                                     值为 null 表示从 manifest 中移除该键
     * @return string 'file' = 已写文件；'override' = tools/ 不可写，降级存 DB
     * @throws RuntimeException 工具目录不存在
     */
    public function write(string $toolId, array $fields): string
    {
        Security::assertToolId($toolId);

        $dir = $this->toolsPath . DIRECTORY_SEPARATOR . $toolId;
        if (!is_dir($dir)) {
            throw new RuntimeException('工具目录不存在: ' . $toolId);
        }

        if ($this->isWritable($toolId)) {
            $current = $this->read($toolId) ?? [];
            $merged = array_filter(
                array_merge($current, $fields),
                static fn (mixed $value): bool => $value !== null
            );
            $ok = @file_put_contents(
                $dir . DIRECTORY_SEPARATOR . 'manifest.json',
                $this->encode($merged),
                LOCK_EX
            );
            if ($ok !== false) {
                return 'file';
            }
            // 写失败（磁盘满 / 权限突变）继续走降级，不让用户编辑丢失
        }

        $this->writeOverride($toolId, $fields);

        return 'override';
    }

    /**
     * 读取某工具的降级覆盖字段。
     *
     * @return array<string, mixed>
     */
    public function overridesFor(string $toolId): array
    {
        if (!Security::isValidToolId($toolId) || !$this->hasDb()) {
            return [];
        }

        $row = $this->db()->fetch(
            'SELECT overrides FROM tool_overrides WHERE tool_id = :id',
            [':id' => $toolId]
        );
        if ($row === null) {
            return [];
        }

        $data = json_decode((string) $row['overrides'], true);

        return is_array($data) ? $data : [];
    }

    /**
     * 增量写入降级覆盖（与已有覆盖合并）。
     *
     * @param array<string, mixed> $fields
     */
    public function writeOverride(string $toolId, array $fields): void
    {
        Security::assertToolId($toolId);

        $merged = array_merge($this->overridesFor($toolId), $fields);
        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('覆盖数据编码失败');
        }

        $db = $this->db();
        $exists = $db->fetch(
            'SELECT tool_id FROM tool_overrides WHERE tool_id = :id',
            [':id' => $toolId]
        ) !== null;

        if ($exists) {
            $db->execute(
                'UPDATE tool_overrides SET overrides = :ov, updated_at = :at WHERE tool_id = :id',
                [':ov' => $json, ':at' => date('Y-m-d H:i:s'), ':id' => $toolId]
            );
        } else {
            $db->execute(
                'INSERT INTO tool_overrides (tool_id, overrides, updated_at) VALUES (:id, :ov, :at)',
                [':id' => $toolId, ':ov' => $json, ':at' => date('Y-m-d H:i:s')]
            );
        }
    }

    /**
     * 清空某工具的降级覆盖。
     */
    public function clearOverride(string $toolId): void
    {
        if (!Security::isValidToolId($toolId) || !$this->hasDb()) {
            return;
        }

        $this->db()->execute(
            'DELETE FROM tool_overrides WHERE tool_id = :id',
            [':id' => $toolId]
        );
    }

    /**
     * 尝试把降级覆盖落回文件（tools/ 恢复可写时调用）。
     *
     * 成功落盘后清空覆盖记录；失败则保留覆盖，下次再试。
     */
    public function flushOverride(string $toolId): bool
    {
        $overrides = $this->overridesFor($toolId);
        if ($overrides === []) {
            return true;
        }

        $current = $this->read($toolId);
        if (!$this->isWritable($toolId) || $current === null) {
            return false;
        }

        $ok = @file_put_contents(
            $this->toolsPath . DIRECTORY_SEPARATOR . $toolId . DIRECTORY_SEPARATOR . 'manifest.json',
            $this->encode(array_merge($current, $overrides)),
            LOCK_EX
        );
        if ($ok === false) {
            return false;
        }

        $this->clearOverride($toolId);

        return true;
    }

    /**
     * 将降级覆盖合并到 manifest 数据上（扫描器用）。
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function applyOverrides(array $manifest, array $overrides): array
    {
        return array_merge($manifest, $overrides);
    }

    /**
     * 按规范格式化 manifest：2 空格缩进、LF、字段顺序稳定、末尾空行。
     *
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        $ordered = [];
        foreach (self::FIELD_ORDER as $key) {
            if (array_key_exists($key, $data)) {
                $ordered[$key] = $data[$key];
            }
        }
        // 规范未定义的字段（向前兼容）附加在末尾
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        $json = json_encode(
            $ordered,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            throw new RuntimeException('manifest 编码失败');
        }

        // PHP pretty print 固定 4 空格缩进，规范要求 2 空格。
        // 换行只会出现在行首缩进处（JSON 字符串内的换行必被转义），
        // 因此按行减半缩进是安全的。
        $json = preg_replace_callback(
            '/^( +)/m',
            static fn (array $m): string => str_repeat(' ', intdiv(strlen($m[1]), 2)),
            $json
        ) ?? $json;

        // PHP 会把所有数组强制拆成多行，标量数组折叠回单行，
        // 避免每次回写都对纯标量数组产生无意义的 git diff
        $json = preg_replace_callback(
            '/\[((?:[^\[\]])*)\]/s',
            static function (array $m): string {
                $inner = trim($m[1]);
                if ($inner === '') {
                    return '[]';
                }
                $parts = array_map(static fn (string $line): string => trim($line), explode("\n", $inner));
                $scalar = '/^(?:"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?|true|false|null)$/';
                foreach ($parts as $part) {
                    if (preg_match($scalar, rtrim($part, ',')) !== 1) {
                        return $m[0]; // 含对象 / 嵌套数组，保持多行
                    }
                }

                return '[' . implode(', ', array_map(
                    static fn (string $part): string => rtrim($part, ','),
                    $parts
                )) . ']';
            },
            $json
        ) ?? $json;

        $json = str_replace("\r\n", "\n", $json);

        return $json . "\n";
    }

    private function hasDb(): bool
    {
        return $this->db !== null || App::hasDb();
    }

    private function db(): Database
    {
        return $this->db ?? App::db();
    }
}
