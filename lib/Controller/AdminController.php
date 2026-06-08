<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Controller;

use OCA\BesteSchule\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Admin page controller.
 * The actual admin settings panel is handled by Settings\AdminSettings;
 * this controller serves a standalone admin page reachable via navigation.
 */
class AdminController extends Controller
{
    public function __construct(
        IRequest $request,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoCSRFRequired]
    public function index(): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'admin');
    }
}
