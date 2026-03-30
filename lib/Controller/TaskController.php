<?php
namespace OCA\UrbanDuplicati\Controller;

use OCA\UrbanDuplicati\Db\Db;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

class TaskController extends Controller {
    private Db $db;
    private IUserSession $userSession;
    private IRootFolder $rootFolder;

    public function __construct(
        string $appName, IRequest $request,
        Db $db, IUserSession $userSession, IRootFolder $rootFolder
    ) {
        parent::__construct($appName, $request);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
    }

    private function uid(): string {
        return $this->userSession->getUser()->getUID();
    }

    private function getProtectionRules(): array {
        $rules = $this->db->fetchAll(
            'SELECT path, is_recursive FROM oc_ud_protection WHERE user_id = ? OR scope = ?',
            [$this->uid(), 'admin']
        );
        // Get external mount points to resolve paths
        try {
            $mounts = $this->db->fetchAll('SELECT mount_point FROM oc_external_mounts');
            $mountPoints = array_map(fn($m) => rtrim($m['mount_point'], '/'), $mounts);
        } catch (\Exception $e) {
            $mountPoints = [];
        }
        foreach ($rules as &$rule) {
            $rule['resolved_paths'] = $this->resolveProtectionPath($rule['path'], $mountPoints);
        }
        return $rules;
    }

    private function resolveProtectionPath(string $path, array $mountPoints): array {
        $path = '/' . ltrim($path, '/');
        $candidates = [$path, ltrim($path, '/')];
        foreach ($mountPoints as $mount) {
            $mount = '/' . ltrim($mount, '/');
            if (strpos($path, $mount . '/') === 0) {
                $stripped = substr($path, strlen($mount) + 1);
                $candidates[] = $stripped;
                $candidates[] = ltrim($stripped, '/');
            }
        }
        foreach ($candidates as $c) {
            if (strpos($c, 'files/') === 0) {
                $candidates[] = substr($c, 6);
            }
        }
        return array_unique(array_filter($candidates));
    }

    private function isProtected(array $file, array $rules): bool {
        $fp = $file['filepath'] ?? '';
        foreach ($rules as $rule) {
            $recursive = (bool)$rule['is_recursive'];
            $paths = $rule['resolved_paths'] ?? [$rule['path']];
            foreach ($paths as $rp) {
                $rp = rtrim($rp, '/');
                if ($recursive) {
                    if ($fp === $rp || strpos($fp, $rp . '/') === 0) return true;
                } else {
                    if ($fp === $rp) return true;
                }
            }
        }
        return false;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse {
        $rows = $this->db->fetchAll(
            'SELECT * FROM oc_ud_tasks WHERE user_id = ? ORDER BY created_time DESC',
            [$this->uid()]
        );
        return new JSONResponse(['tasks' => $rows]);
    }

    /**
     * @NoAdminRequired
     */
    public function run(): JSONResponse {
        $uid      = $this->uid();
        $data     = $this->request->getParams();
        $dirs     = json_decode($data['targetDirectoryIds'] ?? '[]', true) ?: [];
        $settings = json_decode($data['collectorSettings'] ?? '{}', true) ?: [];
        $name     = $data['name'] ?? ('Scan ' . date('Y-m-d H:i'));
        $now      = time();

        $filesTotal = 0;
        $filesTotalSize = 0;
        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
            $mtype = (int)($settings['target_mtype'] ?? 2);
            $mimes = $mtype === 0 ? ['image'] : ($mtype === 1 ? ['video'] : ['image', 'video']);
            foreach ($dirs as $dirId) {
                $nodes = $userFolder->getById((int)$dirId);
                foreach ($nodes as $node) {
                    if ($node instanceof \OCP\Files\Folder) {
                        $filesTotal += $this->countFiles($node, $mimes);
                        $filesTotalSize += $node->getSize();
                    }
                }
            }
        } catch (\Exception $e) {}

        $this->db->execute(
            'INSERT INTO oc_ud_tasks
             (user_id, name, created_time, target_directory_ids, collector_settings,
              files_scanned, files_total, files_total_size, py_pid, errors)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, 0, ?)',
            [$uid, $name, $now, json_encode(array_map('intval', $dirs)),
             json_encode($settings), $filesTotal, $filesTotalSize, '']
        );
        $taskId = $this->db->lastInsertId('oc_ud_tasks');

        $appDir     = \OC_App::getAppPath('urbanduplicati');
        $serverRoot = \OC::$SERVERROOT;
        $dataDir    = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/html/data');
        $instanceId = \OC::$server->getConfig()->getSystemValue('instanceid', '');
        $logDir     = $dataDir . '/appdata_' . $instanceId . '/urbanduplicati/logs';
        @mkdir($logDir, 0750, true);
        $logFile = $logDir . '/' . date('d-m-Y_H-i-s') . '_task' . $taskId . '.log';

        $cmd = sprintf(
            'SERVER_ROOT=%s UD_LOGLEVEL=INFO nohup /usr/bin/python3 %s/python/main.py -t %d > %s 2>&1 &',
            escapeshellarg($serverRoot),
            escapeshellarg($appDir),
            $taskId,
            escapeshellarg($logFile)
        );
        exec($cmd);

        return new JSONResponse(['success' => true, 'task_id' => $taskId]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function show(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT * FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);
        return new JSONResponse($row);
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT id, py_pid FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);
        $pid = (int)$row['py_pid'];
        if ($pid > 0) posix_kill($pid, SIGTERM);
        $this->db->execute('DELETE FROM oc_ud_group_files WHERE task_id = ?', [$id]);
        $this->db->execute('DELETE FROM oc_ud_groups WHERE task_id = ?', [$id]);
        $this->db->execute('DELETE FROM oc_ud_tasks WHERE id = ?', [$id]);
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     */
    public function terminate(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT py_pid FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);
        $pid = (int)$row['py_pid'];
        if ($pid > 0) posix_kill($pid, SIGTERM);
        $this->db->execute(
            'UPDATE oc_ud_tasks SET py_pid = 0, finished_time = ?, errors = ? WHERE id = ?',
            [time(), 'stopped by user', $id]
        );
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function groups(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT id FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);

        $page   = max(0, (int)$this->request->getParam('page', 0));
        $limit  = max(1, min(100, (int)$this->request->getParam('limit', 20)));
        $offset = $page * $limit;
        $filter = $this->request->getParam('filter', '');

        $groups = $this->db->fetchAll(
            "SELECT group_id, hash FROM oc_ud_groups WHERE task_id = ? ORDER BY group_id LIMIT {$limit} OFFSET {$offset}",
            [$id]
        );

        $rules = $this->getProtectionRules();

        foreach ($groups as &$group) {
            $files = $this->db->fetchAll(
                'SELECT id, fileid, filename, filepath, filesize FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?',
                [$id, $group['group_id']]
            );
            // Annotate each file with protection status and filter match
            foreach ($files as &$file) {
                $file['protected'] = $this->isProtected($file, $rules);
                $file['filter_match'] = !empty($filter) ? fnmatch($filter, $file['filename']) : false;
            }
            $group['files'] = $files;

            // Count protected files in this group
            $protectedCount = count(array_filter($files, fn($f) => $f['protected']));
            $group['all_protected'] = $protectedCount === count($files);
            $group['has_protected_duplicates'] = $protectedCount >= 2;
            $group['has_filter_match'] = !empty($filter) && count(array_filter($files, fn($f) => $f['filter_match'])) > 0;
        }

        // If filter active, remove groups with no matching files
        if (!empty($filter)) {
            $groups = array_values(array_filter($groups, fn($g) => $g['has_filter_match']));
        }

        $totals = $this->db->fetchOne(
            'SELECT COUNT(DISTINCT g.group_id) as groupstotal,
                    COUNT(f.id) as filestotal,
                    COALESCE(SUM(f.filesize), 0) as filessize
             FROM oc_ud_groups g
             LEFT JOIN oc_ud_group_files f ON f.group_id = g.group_id AND f.task_id = g.task_id
             WHERE g.task_id = ?',
            [$id]
        );

        return new JSONResponse(['groups' => $groups, 'totals' => $totals]);
    }

    /**
     * Returns ALL group IDs for a task (for select-all-across-pages)
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function allGroupIds(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT id FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT group_id FROM oc_ud_groups WHERE task_id = ? ORDER BY group_id',
            [$id]
        );
        return new JSONResponse(['group_ids' => array_column($rows, 'group_id')]);
    }

    /**
     * @NoAdminRequired
     */
    public function deleteFile(int $id, int $groupId, int $fileId): JSONResponse {
        $file = $this->db->fetchOne(
            'SELECT f.fileid, f.filepath, f.filename, f.filesize
             FROM oc_ud_group_files f
             JOIN oc_ud_tasks t ON t.id = f.task_id
             WHERE f.id = ? AND f.task_id = ? AND f.group_id = ? AND t.user_id = ?',
            [$fileId, $id, $groupId, $this->uid()]
        );
        if (!$file) return new JSONResponse(['error' => 'Not found'], 404);

        $rules = $this->getProtectionRules();
        if ($this->isProtected($file, $rules)) {
            return new JSONResponse(['error' => 'File is protected'], 403);
        }

        try {
            $userFolder = $this->rootFolder->getUserFolder($this->uid());
            $nodes = $userFolder->getById((int)$file['fileid']);
            if (!empty($nodes)) $nodes[0]->delete();
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }

        $this->db->execute('DELETE FROM oc_ud_group_files WHERE id = ?', [$fileId]);
        $this->db->execute(
            'INSERT INTO oc_ud_audit (task_id, group_id, file_path, file_size, action, user_id, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $groupId, $file['filepath'].'/'.$file['filename'], $file['filesize'], 'deleted', $this->uid(), 'manual', time()]
        );

        // Clean up group if ≤1 file remains
        $remaining = $this->db->fetchOne(
            'SELECT COUNT(*) as cnt FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?',
            [$id, $groupId]
        );
        if ((int)$remaining['cnt'] <= 1) {
            $this->db->execute('DELETE FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?', [$id, $groupId]);
            $this->db->execute('DELETE FROM oc_ud_groups WHERE task_id = ? AND group_id = ?', [$id, $groupId]);
        }

        return new JSONResponse(['success' => true]);
    }

    /**
     * Bulk delete:
     * - Keep ALL protected files always
     * - Delete ALL unprotected files in the group (none are kept from unprotected)
     * - If deleteProtectedDuplicates=true for specific groups, also delete all-but-one protected files
     *
     * @NoAdminRequired
     */
    public function bulkDelete(int $id): JSONResponse {
        try {
        $groupIds = $this->request->getParam('group_ids', []);
        // Optional: list of group IDs where user chose to also delete protected duplicates (keep one)
        $deleteProtectedFor = $this->request->getParam('delete_protected_for', []);
        $deleteUnprotectedKeepOne = (bool)$this->request->getParam('delete_unprotected_keep_one', false);
        $keepFromFolder = (string)$this->request->getParam('keep_from_folder', '');
        $filterPattern = (string)$this->request->getParam('filter_pattern', '');

        if (empty($groupIds)) return new JSONResponse(['error' => 'No groups'], 400);

        $task = $this->db->fetchOne(
            'SELECT id FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$task) return new JSONResponse(['error' => 'Not found'], 404);

        $rules = $this->getProtectionRules();
        $deleted = 0;
        $skipped = 0;

        foreach ($groupIds as $gid) {
            $files = $this->db->fetchAll(
                'SELECT id, fileid, filepath, filename, filesize FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?',
                [$id, $gid]
            );

            $protected   = array_values(array_filter($files, fn($f) => $this->isProtected($f, $rules)));
            $unprotected = array_values(array_filter($files, fn($f) => !$this->isProtected($f, $rules)));

               // 1. Determine files to delete based on filter or default logic
            if (!empty($filterPattern)) {
                // Filter mode: delete files matching the glob pattern (skip protected unless keepFromFolder set)
                $toDelete = array_values(array_filter($files, function($f) use ($filterPattern, $rules) {
                    if ($this->isProtected($f, $rules)) return false; // respect protection by default
                    return fnmatch($filterPattern, $f['filename']);
                }));
            } else {
                // Default: delete all unprotected files
                $toDelete = $unprotected;
            }

            // 2. Per-group opt-in: delete protected duplicates, keep first
            if (in_array((string)$gid, array_map('strval', $deleteProtectedFor)) && count($protected) >= 2) {
                $toDelete = array_merge($toDelete, array_slice($protected, 1));
            }

            // 3. Global keep-from-folder: keep files from chosen folder, delete all others
            if ($deleteUnprotectedKeepOne && !empty($keepFromFolder)) {
                $mounts = [];
                try { $mounts = $this->db->fetchAll('SELECT mount_point FROM oc_external_mounts'); } catch (\Exception $e) {}
                $mountPoints = array_map(fn($m) => rtrim($m['mount_point'], '/'), $mounts);
                $resolvedKeep = $this->resolveProtectionPath($keepFromFolder, $mountPoints);

                $allFiles = array_merge($unprotected, $protected);
                $inKeep = array_values(array_filter($allFiles, function($f) use ($resolvedKeep) {
                    $fp = $f['filepath'] ?? '';
                    foreach ($resolvedKeep as $rp) {
                        $rp = rtrim($rp, '/');
                        if ($fp === $rp || strpos($fp, $rp . '/') === 0) return true;
                    }
                    return false;
                }));
                $notInKeep = array_values(array_filter($allFiles, function($f) use ($resolvedKeep) {
                    $fp = $f['filepath'] ?? '';
                    foreach ($resolvedKeep as $rp) {
                        $rp = rtrim($rp, '/');
                        if ($fp === $rp || strpos($fp, $rp . '/') === 0) return false;
                    }
                    return true;
                }));
                $toDelete = array_merge($notInKeep, array_slice($inKeep, 1));
            }

            foreach ($toDelete as $file) {
                try {
                    $userFolder = $this->rootFolder->getUserFolder($this->uid());
                    $nodes = $userFolder->getById((int)$file['fileid']);
                    if (!empty($nodes)) $nodes[0]->delete();
                    $this->db->execute('DELETE FROM oc_ud_group_files WHERE id = ?', [$file['id']]);
                    $this->db->execute(
                        'INSERT INTO oc_ud_audit (task_id, group_id, file_path, file_size, action, user_id, reason, created_at)
                         VALUES (?,?,?,?,?,?,?,?)',
                        [$id, $gid, $file['filepath'].'/'.$file['filename'], $file['filesize'], 'deleted', $this->uid(), 'bulk', time()]
                    );
                    $deleted++;
                } catch (\Exception $e) { $skipped++; }
            }

            // Clean up group if ≤1 file remains
            $remaining = $this->db->fetchOne(
                'SELECT COUNT(*) as cnt FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?',
                [$id, $gid]
            );
            if ((int)$remaining['cnt'] <= 1) {
                $this->db->execute('DELETE FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?', [$id, $gid]);
                $this->db->execute('DELETE FROM oc_ud_groups WHERE task_id = ? AND group_id = ?', [$id, $gid]);
            }
        }

        return new JSONResponse(['success' => true, 'deleted' => $deleted, 'skipped' => $skipped]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function bulkRemove(int $id): JSONResponse {
        $groupIds = $this->request->getParam('group_ids', []);
        if (empty($groupIds)) return new JSONResponse(['error' => 'No groups'], 400);
        foreach ($groupIds as $gid) {
            $this->db->execute('DELETE FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?', [$id, $gid]);
            $this->db->execute('DELETE FROM oc_ud_groups WHERE task_id = ? AND group_id = ?', [$id, $gid]);
        }
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     */
    public function dryRun(int $id): JSONResponse {
        $groupIds = $this->request->getParam('group_ids', []);
        $deleteProtectedFor = $this->request->getParam('delete_protected_for', []);

        $task = $this->db->fetchOne(
            'SELECT id FROM oc_ud_tasks WHERE id = ? AND user_id = ?',
            [$id, $this->uid()]
        );
        if (!$task) return new JSONResponse(['error' => 'Not found'], 404);

        $rules = $this->getProtectionRules();
        $preview = [];

        foreach ($groupIds as $gid) {
            $files = $this->db->fetchAll(
                'SELECT id, fileid, filepath, filename, filesize FROM oc_ud_group_files WHERE task_id = ? AND group_id = ?',
                [$id, $gid]
            );

            $protected   = array_values(array_filter($files, fn($f) => $this->isProtected($f, $rules)));
            $unprotected = array_values(array_filter($files, fn($f) => !$this->isProtected($f, $rules)));

            $toDelete = $unprotected;
            if (in_array((string)$gid, array_map('strval', $deleteProtectedFor)) && count($protected) >= 2) {
                $toDelete = array_merge($toDelete, array_slice($protected, 1));
            }

            $del  = array_map(fn($f) => ['fileid' => $f['fileid'], 'path' => $f['filepath'].'/'.$f['filename'], 'size' => (int)$f['filesize']], $toDelete);
            $skip = array_map(fn($f) => ['fileid' => $f['fileid'], 'path' => $f['filepath'].'/'.$f['filename'], 'size' => (int)$f['filesize'], 'reason' => 'protected'], array_diff_key($protected, array_flip(range(0, count($toDelete)-1))));

            $preview[] = [
                'group_id'               => $gid,
                'delete'                 => $del,
                'skip_protected'         => $skip,
                'has_protected_dupes'    => count($protected) >= 2,
            ];
        }

        return new JSONResponse(['preview' => $preview]);
    }

    private function countFiles(\OCP\Files\Folder $folder, array $mimes): int {
        $count = 0;
        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof \OCP\Files\File) {
                if (in_array($node->getMimePart(), $mimes)) $count++;
            } elseif ($node instanceof \OCP\Files\Folder) {
                $count += $this->countFiles($node, $mimes);
            }
        }
        return $count;
    }
}
