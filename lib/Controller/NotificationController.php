<?php
namespace OCA\UrbanDuplicati\Controller;
use OCA\UrbanDuplicati\Db\Db;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Notification\IManager;
class NotificationController extends Controller {
    private IManager $notificationManager;
    private Db $db;
    public function __construct(string $appName, IRequest $request, IManager $notificationManager, Db $db) {
        parent::__construct($appName, $request);
        $this->notificationManager = $notificationManager;
        $this->db = $db;
    }
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function scanFinished(int $taskId): JSONResponse {
        $secret = $this->request->getParam("secret", "");
        $stored = \OC::$server->getConfig()->getAppValue("urbanduplicati", "internal_secret", "");
        if (empty($stored) || $secret !== $stored) return new JSONResponse(["error" => "Unauthorized"], 401);
        $task = $this->db->fetchOne("SELECT * FROM oc_ud_tasks WHERE id = ?", [$taskId]);
        if (!$task) return new JSONResponse(["error" => "Task not found"], 404);
        $settings = json_decode($task["collector_settings"] ?? "{}", true);
        if (empty($settings["finish_notification"])) return new JSONResponse(["success" => true, "notified" => false]);
        $row = $this->db->fetchOne("SELECT COUNT(DISTINCT group_id) as cnt FROM oc_ud_groups WHERE task_id = ?", [$taskId]);
        $groupCount = (int)($row["cnt"] ?? 0);
        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp("urbanduplicati")
            ->setUser($task["user_id"])
            ->setDateTime(new \DateTime())
            ->setObject("task", (string)$taskId)
            ->setSubject("scan_finished", [
                "task_name"   => $task["name"] ?? ("Scan #" . $taskId),
                "group_count" => $groupCount,
            ]);
        $this->notificationManager->notify($notification);
        return new JSONResponse(["success" => true, "notified" => true, "groups" => $groupCount]);
    }
}
