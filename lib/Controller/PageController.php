<?php
namespace OCA\UrbanDuplicati\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        return new TemplateResponse('urbanduplicati', 'main');
    }

    /**
     * @NoCSRFRequired
     */
    public function admin(): TemplateResponse {
        return new TemplateResponse('urbanduplicati', 'admin');
    }
}
