<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Settings;

use OCA\BesteSchule\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * Integrates the personal account setup into Nextcloud's Settings → Personal panel.
 */
class PersonalSettings implements ISettings
{
    public function getForm(): TemplateResponse
    {
        return new TemplateResponse(Application::APP_ID, 'personal', [], 'user');
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
