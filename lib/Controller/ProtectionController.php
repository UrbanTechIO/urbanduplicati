<?php
namespace OCA\UrbanDuplicati\Controller;

use OCA\UrbanDuplicati\Db\Db;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ProtectionController extends Controller {
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
        $rows = $this->db->fetchAll(
            'SELECT * FROM oc_ud_protection WHERE user_id = ? OR scope = ? ORDER BY created_at DESC',
            [$this->uid(), 'admin']
        );
        return new JSONResponse(['rules' => $rows]);
    }

    /**
     * @NoAdminRequired
     */
    public function create(): JSONResponse {
        $path      = $this->request->getParam('path', '');
        $label     = $this->request->getParam('label', '');
        $recursive = (bool)$this->request->getParam('recursive', true);
        $scope     = $this->request->getParam('scope', 'user');
        if (!$path) return new JSONResponse(['error' => 'Path required'], 400);
        if ($scope !== 'admin') $scope = 'user';
        $this->db->execute(
            'INSERT INTO oc_ud_protection (user_id, path, label, is_recursive, scope, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$this->uid(), $path, $label, $recursive ? 1 : 0, $scope, time()]
        );
        return new JSONResponse(['success' => true, 'id' => $this->db->lastInsertId('oc_ud_protection')]);
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): JSONResponse {
        $row = $this->db->fetchOne(
            'SELECT id FROM oc_ud_protection WHERE id = ? AND (user_id = ? OR scope = ?)',
            [$id, $this->uid(), 'admin']
        );
        if (!$row) return new JSONResponse(['error' => 'Not found'], 404);
        $this->db->execute('DELETE FROM oc_ud_protection WHERE id = ?', [$id]);
        return new JSONResponse(['success' => true]);
    }
}
