<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Settings;

use OCA\BesteSchule\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * Integrates the admin view into Nextcloud's Settings → Administration panel.
 */
class AdminSettings implements ISettings
{
    public function getForm(): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'admin', [], 'user');
    }

    public function getSection(): string
    {
        return 'connected-accounts';
    }

    public function getPriority(): int
    {
        return 50;
    }
}
