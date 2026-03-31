<?php
namespace OCA\UrbanDuplicati\Notification;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
class Notifier implements INotifier {
    private IFactory $factory;
    private IURLGenerator $url;
    public function __construct(IFactory $factory, IURLGenerator $url) {
        $this->factory = $factory;
        $this->url = $url;
    }
    public function getID(): string { return "urbanduplicati"; }
    public function getName(): string { return "Dupli"; }
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== "urbanduplicati") throw new \InvalidArgumentException("Unknown app");
        if ($notification->getSubject() !== "scan_finished") throw new \InvalidArgumentException("Unknown subject");
        $params = $notification->getSubjectParameters();
        $taskName = $params["task_name"] ?? "Scan";
        $groupCount = (int)($params["group_count"] ?? 0);
        $msg = $groupCount > 0
            ? "Dupli: " . chr(39) . $taskName . chr(39) . " finished — {$groupCount} duplicate groups found"
            : "Dupli: " . chr(39) . $taskName . chr(39) . " finished — no duplicates found";
        $notification->setParsedSubject($msg);
        $notification->setLink($this->url->linkToRouteAbsolute("urbanduplicati.page.index"));
        $notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath("urbanduplicati", "app-dark.svg")));
        return $notification;
    }
}
