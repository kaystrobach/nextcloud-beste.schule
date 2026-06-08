<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Stores a beste.schule account linked to a Nextcloud user.
 *
 * @method string  getUserId()
 * @method void    setUserId(string $userId)
 * @method string  getAccessToken()
 * @method void    setAccessToken(string $token)
 * @method int     getStudentId()
 * @method void    setStudentId(int $id)
 * @method string  getStudentName()
 * @method void    setStudentName(string $name)
 * @method int     getIntervalId()
 * @method void    setIntervalId(int $id)
 * @method string|null getCalendarUri()
 * @method void    setCalendarUri(?string $uri)
 * @method int     getSyncInterval()
 * @method void    setSyncInterval(int $hours)
 * @method string|null getAddress()
 * @method void    setAddress(?string $address)
 * @method string|null getLastSyncAt()
 * @method void    setLastSyncAt(?string $ts)
 * @method string|null getLastSyncError()
 * @method void    setLastSyncError(?string $err)
 */
class Account extends Entity {
    protected string $userId      = '';
    protected string $accessToken = '';
    protected int    $studentId   = 0;
    protected string $studentName = '';
    protected int    $intervalId  = 0;
    protected ?string $calendarUri  = null;
    protected int    $syncInterval = 24;  // hours between background syncs
    protected ?string $address      = null;
    protected ?string $lastSyncAt   = null;
    protected ?string $lastSyncError = null;

    public function __construct() {
        $this->addType('studentId',    'integer');
        $this->addType('intervalId',   'integer');
        $this->addType('syncInterval', 'integer');
    }
}
