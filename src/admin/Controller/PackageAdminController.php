<?php
declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\AdminController;
use App\Core\App;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PackageBuilder;
use ZipArchive;

/**
 * 离线包打包（P3，仅 admin —— editor 无权）
 *
 * 打包为异步任务模式：create 只落任务行；前端轮询 status 端点，
 * 该端点惰性消费 pending 任务（无 cron 也能工作），也可由
 * scripts/build_package.php CLI 消费。同时只允许 1 个任务（DB + 文件锁）。
 */
final class PackageAdminController extends AdminController
{
    private PackageBuilder $builder;

    public function __construct()
    {
        $this->builder = new PackageBuilder();
    }

    public function index(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->render('packages', ['dbReady' => false], 'packages');
        }

        $db = App::db();

        // 可打包工具：已上架 + 单文件 + 离线可用，附文件实际大小（前端实时预估包体）
        $tools = [];
        foreach ($db->fetchAll(
            'SELECT tool_id, title, version, type, dir_path, entry
             FROM tools WHERE is_published = 1 AND single_file = 1 AND offline = 1
             ORDER BY title ASC'
        ) as $row) {
            $bytes = 0;
            $path = App::path('tools') . DIRECTORY_SEPARATOR . $row['dir_path'] . DIRECTORY_SEPARATOR . $row['entry'];
            if (is_file($path)) {
                $bytes = (int) (filesize($path) ?: 0);
            }
            $tools[] = [
                'tool_id'  => (string) $row['tool_id'],
                'title'    => (string) $row['title'],
                'version'  => (string) $row['version'],
                'bytes'    => $bytes,
            ];
        }

        $packages = [];
        foreach ($db->fetchAll('SELECT * FROM packages ORDER BY id DESC') as $row) {
            $packages[] = $this->hydratePackage($row);
        }

        return $this->render('packages', [
            'pageTitle' => '离线包打包 — ' . site_name(),
            'dbReady'   => true,
            'tools'     => $tools,
            'packages'  => $packages,
            'task'      => $this->builder->latestTask(),
            'zipReady'  => class_exists(ZipArchive::class),
            'maxSizeMb' => Config::int('PACKAGE_MAX_SIZE_MB', 10),
        ], 'packages');
    }

    public function create(Request $request): Response
    {
        if (!$this->dbReady()) {
            return $this->redirectWith('error', '数据库未初始化', '/admin/package');
        }

        try {
            // tool_ids 是 checkbox 数组，post() 只取标量，须走 allPost()
            $toolIds = $request->allPost()['tool_ids'] ?? [];
            $toolIds = is_array($toolIds) ? array_map('strval', $toolIds) : [];
            $taskId = $this->builder->createTask(
                (string) ($request->post('slug') ?? ''),
                (string) ($request->post('version') ?? ''),
                $toolIds,
                (string) ($request->post('with_portal') ?? '') === '1',
                (string) (Session::get('admin_user') ?? '')
            );
        } catch (\Throwable $e) {
            return $this->redirectWith('error', $e->getMessage(), '/admin/package');
        }

        return $this->redirectWith('success', "打包任务 #{$taskId} 已创建，开始执行…", '/admin/package');
    }    /**
     * 任务状态轮询（前端 fetch，X-Requested-With）。
     * 惰性消费：存在 pending 且无 running 任务时就地执行。
     */
    public function status(Request $request): Response
    {
        if (!$this->dbReady() || !$request->isAjax()) {
            return Response::json(['error' => '非法请求'], 400);
        }

        $this->builder->cleanupStale();
        $this->builder->processPending();

        $task = $this->builder->latestTask();
        $payload = ['task' => $task, 'package' => null];
        if ($task !== null && $task['status'] === 'done' && $task['package_id'] !== null) {
            $row = App::db()->fetch(
                'SELECT * FROM packages WHERE id = :id',
                [':id' => $task['package_id']]
            );
            if ($row !== null) {
                $payload['package'] = $this->hydratePackage($row);
            }
        }

        return Response::json($payload);
    }

    public function download(Request $request): Response
    {
        $package = $this->resolvePackage($request);
        if ($package instanceof Response) {
            return $package;
        }

        $path = $this->builder->packagePath((string) $package['file_name']);
        if (!is_file($path)) {
            return $this->redirectWith('error', '产物文件已不在磁盘上，请重新生成', '/admin/package');
        }

        return Response::download($path);
    }

    public function delete(Request $request): Response
    {
        $package = $this->resolvePackage($request);
        if ($package instanceof Response) {
            return $package;
        }

        try {
            $this->builder->deletePackage((int) $package['id']);
        } catch (\Throwable $e) {
            return $this->redirectWith('error', $e->getMessage(), '/admin/package');
        }

        return $this->redirectWith('success', '产物已删除。', '/admin/package');
    }

    /**
     * 重新生成：按原 slug / 版本 / 工具清单创建新任务。
     */
    public function regen(Request $request): Response
    {
        $package = $this->resolvePackage($request);
        if ($package instanceof Response) {
            return $package;
        }

        try {
            $this->builder->createTask(
                (string) $package['slug'],
                (string) $package['version'],
                (array) $package['tool_ids'],
                true
            );
        } catch (\Throwable $e) {
            return $this->redirectWith('error', $e->getMessage(), '/admin/package');
        }

        return $this->redirectWith('success', '已按原配置创建重新打包任务。', '/admin/package');
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function resolvePackage(Request $request): array|Response
    {
        $id = (int) $request->attribute('id', 0);
        $row = App::db()->fetch('SELECT * FROM packages WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            return $this->redirectWith('error', '产物不存在', '/admin/package');
        }

        return $this->hydratePackage($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydratePackage(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['tool_count'] = (int) $row['tool_count'];
        $row['size_bytes'] = (int) $row['size_bytes'];
        $decoded = json_decode((string) $row['tool_ids'], true);
        $row['tool_ids'] = is_array($decoded) ? array_values($decoded) : [];
        $row['exists'] = is_file($this->builder->packagePath((string) $row['file_name']));

        return $row;
    }
}
