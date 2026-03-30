<?php
namespace OCA\UrbanDuplicati\Controller;

use OCA\UrbanDuplicati\Db\Db;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AuditController extends Controller {
    private Db $db;
    private IUserSession $userSession;

    public function __construct(string $appName, IRequest $request, Db $db, IUserSession $userSession) {
        parent::__construct($appName, $request);
        $this->db = $db;
        $this->userSession = $userSession;
    }

    private function uid(): string { return $this->userSession->getUser()->getUID(); }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse {
        $page   = max(0, (int)$this->request->getParam('page', 0));
        $limit  = 50;
        $offset = $page * $limit;
        $action = $this->request->getParam('action');

        $where = 'WHERE user_id = ?';
        $params = [$this->uid()];
        if ($action) { $where .= ' AND action = ?'; $params[] = $action; }

        $entries = $this->db->fetchAll(
            "SELECT * FROM oc_ud_audit $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset",
            $params
        );

        $statRows = $this->db->fetchAll(
            'SELECT action, COUNT(*) as count, COALESCE(SUM(file_size),0) as total_size FROM oc_ud_audit WHERE user_id = ? GROUP BY action',
            [$this->uid()]
        );
        $stats = [];
        foreach ($statRows as $s) $stats[$s['action']] = ['count' => (int)$s['count'], 'total_size' => (int)$s['total_size']];

        return new JSONResponse(['entries' => $entries, 'stats' => $stats]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function export(): DataDownloadResponse {
        $rows = $this->db->fetchAll(
            'SELECT * FROM oc_ud_audit WHERE user_id = ? ORDER BY created_at DESC',
            [$this->uid()]
        );
        $csv = "id,task_id,group_id,file_path,file_size,action,user_id,reason,created_at\n";
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)($v??'')).'"', $r)) . "\n";
        }
        return new DataDownloadResponse($csv, 'ud_audit.csv', 'text/csv');
    }
}
