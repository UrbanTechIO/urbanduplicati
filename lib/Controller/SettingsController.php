<?php
namespace OCA\UrbanDuplicati\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class SettingsController extends Controller {
    private IConfig $config;
    private IUserSession $userSession;

    public function __construct(string $appName, IRequest $request, IConfig $config, IUserSession $userSession) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->userSession = $userSession;
    }

    private function uid(): string { return $this->userSession->getUser()->getUID(); }

    private function defaults(): array {
        return [
            'hashing_algorithm'    => 'dhash',
            'similarity_threshold' => 90,
            'hash_size'            => 16,
            'auto_scan_enabled'    => false,
            'auto_scan_interval'   => 86400,
            'audit_retention_days' => 0,
        ];
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse {
        $settings = $this->defaults();
        foreach (array_keys($settings) as $key) {
            $val = $this->config->getUserValue($this->uid(), 'urbanduplicati', $key, null);
            if ($val !== null) {
                $def = $settings[$key];
                $settings[$key] = is_bool($def) ? (bool)$val : (is_int($def) ? (int)$val : $val);
            }
        }
        return new JSONResponse(['settings' => $settings]);
    }

    /**
     * @NoAdminRequired
     */
    public function update(): JSONResponse {
        $body = $this->request->getParams();
        foreach ($body as $key => $val) {
            if (array_key_exists($key, $this->defaults())) {
                $this->config->setUserValue($this->uid(), 'urbanduplicati', $key, (string)$val);
            }
        }
        return new JSONResponse(['success' => true, 'settings' => $body]);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function system(): JSONResponse {
        $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        return new JSONResponse([
            'php_version'      => PHP_VERSION,
            'gd_enabled'       => extension_loaded('gd'),
            'imagick_enabled'  => extension_loaded('imagick'),
            'ffmpeg_available' => !empty($ffmpeg),
            'ffmpeg_path'      => $ffmpeg ?: null,
        ]);
    }
}
