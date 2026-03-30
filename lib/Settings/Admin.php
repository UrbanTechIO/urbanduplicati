<?php
namespace OCA\UrbanDuplicati\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
    public function getForm(): TemplateResponse {
        return new TemplateResponse('urbanduplicati', 'admin', [], '');
    }
    public function getSection(): string { return 'additional'; }
    public function getPriority(): int   { return 50; }
}
