<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use RuntimeException;

/**
 * 网盘链接读写
 *
 * 前台（中间页 / 详情页）只读活跃链接；后台管理走同一类的 CRUD。
 * 网盘类型取值与标签集中在 NETDISKS 常量，新增网盘只改这一处。
 */
final class NetdiskRepository
{
    /** 支持的网盘类型 => 展示名（顺序即前台展示优先级：限速宽松的在前） */
    public const TYPES = [
        '123'    => '123云盘',
        'quark'  => '夸克网盘',
        'aliyun' => '阿里云盘',
        'baidu'  => '百度网盘',
        'other'  => '其他网盘',
    ];

    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    /**
     * 某工具的网盘链接列表。
     *
     * @return list<array<string, mixed>>
     */
    public function forTool(string $toolId, bool $onlyActive = true): array
    {
        $sql = 'SELECT id, tool_id, netdisk_type, url, extract_code, package_name, file_size,
                       is_active, last_checked_at, check_status
                FROM tool_netdisks WHERE tool_id = :tool';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY CASE netdisk_type ';

        // 按 TYPES 顺序排序：主推网盘排前
        $cases = '';
        $params = [':tool' => $toolId];
        foreach (array_keys(self::TYPES) as $i => $type) {
            $key = ':t' . $i;
            $cases .= 'WHEN ' . $key . ' THEN ' . $i . ' ';
            $params[$key] = $type;
        }
        $sql .= $cases . 'ELSE 99 END, id ASC';

        $rows = $this->db()->fetchAll($sql, $params);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * 按类型取单个活跃链接（中间页）。
     *
     * @return array<string, mixed>|null
     */
    public function findActive(string $toolId, string $type): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, tool_id, netdisk_type, url, extract_code, package_name, file_size,
                    is_active, last_checked_at, check_status
             FROM tool_netdisks
             WHERE tool_id = :tool AND netdisk_type = :type AND is_active = 1
             LIMIT 1',
            [':tool' => $toolId, ':type' => $type]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * 后台：按 id 取（不限工具）。
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->fetch('SELECT * FROM tool_netdisks WHERE id = :id', [':id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * 后台：新增 / 更新。$id 为 null 时新增。
     *
     * @return int 记录 id
     */
    public function save(string $toolId, string $type, string $url, ?string $extractCode, ?string $packageName, ?int $id = null): int
    {
        if ($id === null) {
            return $this->db()->insert(
                'INSERT INTO tool_netdisks (tool_id, netdisk_type, url, extract_code, package_name, is_active)
                 VALUES (:tool, :type, :url, :code, :pkg, 1)',
                [
                    ':tool' => $toolId, ':type' => $type, ':url' => $url,
                    ':code' => $extractCode, ':pkg' => $packageName,
                ]
            );
        }

        $this->db()->execute(
            'UPDATE tool_netdisks
             SET netdisk_type = :type, url = :url, extract_code = :code, package_name = :pkg
             WHERE id = :id',
            [
                ':type' => $type, ':url' => $url, ':code' => $extractCode,
                ':pkg' => $packageName, ':id' => $id,
            ]
        );

        return $id;
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db()->execute(
            'UPDATE tool_netdisks SET is_active = :a WHERE id = :id',
            [':a' => $active ? 1 : 0, ':id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db()->execute('DELETE FROM tool_netdisks WHERE id = :id', [':id' => $id]);
    }

    public function markChecked(int $id, string $status): void
    {
        $this->db()->execute(
            'UPDATE tool_netdisks SET last_checked_at = :now, check_status = :status WHERE id = :id',
            [':now' => date('Y-m-d H:i:s'), ':status' => $status, ':id' => $id]
        );
    }

    /**
     * 探活待办：活跃但从未探活或超期未探活的链接。
     *
     * @return list<array<string, mixed>>
     */
    public function dueForCheck(int $limit = 50): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT id, tool_id, netdisk_type, url FROM tool_netdisks
             WHERE is_active = 1
               AND (last_checked_at IS NULL OR last_checked_at < :before)
             ORDER BY last_checked_at IS NOT NULL, id ASC
             LIMIT " . (int) $limit,
            [':before' => date('Y-m-d H:i:s', time() - 86400)]
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        $row['type_label'] = self::TYPES[$row['netdisk_type']] ?? (string) $row['netdisk_type'];

        return $row;
    }

    private function db(): Database
    {
        if ($this->db !== null) {
            return $this->db;
        }
        if (!App::hasDb()) {
            throw new RuntimeException('数据库未初始化');
        }

        return App::db();
    }
}
