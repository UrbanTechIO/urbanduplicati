<?php
namespace OCA\UrbanDuplicati\AppInfo;
use OCA\UrbanDuplicati\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
class Application extends App implements IBootstrap {
    public const APP_ID = "urbanduplicati";
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);
    }
    public function boot(IBootContext $context): void {
        require_once \OC_App::getAppPath(self::APP_ID) . "/vendor/autoload.php";
    }
}
