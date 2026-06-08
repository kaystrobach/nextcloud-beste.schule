<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Controller;

use OCA\BesteSchule\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

/**
 * Serves the main grades view for the current user.
 */
class GradesController extends Controller
{
    public function __construct(
        IRequest $request,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'grades');
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(): TemplateResponse
    {
        return $this->index();
    }
}
