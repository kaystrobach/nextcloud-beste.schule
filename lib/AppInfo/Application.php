<?php
declare(strict_types=1);

namespace OCA\BesteSchule\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'beste_schule';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // All services are auto-wired via DI container; nothing extra needed here.
    }

    public function boot(IBootContext $context): void {
        // Nothing to do on boot.
    }
}
