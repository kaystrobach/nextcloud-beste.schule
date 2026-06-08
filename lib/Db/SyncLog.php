<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int     getAccountId()
 * @method void    setAccountId(int $id)
 * @method string  getCreatedAt()
 * @method void    setCreatedAt(string $ts)
 * @method string  getLevel()
 * @method void    setLevel(string $level)
 * @method string  getMessage()
 * @method void    setMessage(string $msg)
 */
class SyncLog extends Entity {
    protected int    $accountId = 0;
    protected string $createdAt = '';
    protected string $level     = 'info';
    protected string $message   = '';

    public function __construct() {
        $this->addType('accountId', 'integer');
    }
}
