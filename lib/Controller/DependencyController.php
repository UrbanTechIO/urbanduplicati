<?php
namespace OCA\UrbanDuplicati\Controller;

use OCA\UrbanDuplicati\Service\DependencyChecker;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class DependencyController extends Controller {
    private DependencyChecker $checker;

    public function __construct(string $appName, IRequest $request, DependencyChecker $checker) {
        parent::__construct($appName, $request);
        $this->checker = $checker;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): JSONResponse {
        return new JSONResponse($this->checker->check());
    }
}
