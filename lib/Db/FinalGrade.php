<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Cached final grade (Endnote) from beste.schule.
 *
 * @method int     getAccountId()
 * @method void    setAccountId(int $id)
 * @method int     getExternalId()
 * @method void    setExternalId(int $id)
 * @method string  getSubjectName()
 * @method void    setSubjectName(string $name)
 * @method int|null getIntervalId()
 * @method void    setIntervalId(?int $id)
 * @method string|null getIntervalName()
 * @method void    setIntervalName(?string $name)
 * @method string  getValue()
 * @method void    setValue(string $v)
 * @method string|null getValueCalc()
 * @method void    setValueCalc(?string $v)
 */
class FinalGrade extends Entity {
    protected int     $accountId    = 0;
    protected int     $externalId   = 0;
    protected string  $subjectName  = '';
    protected ?int    $intervalId   = null;
    protected ?string $intervalName = null;
    protected string  $value        = '';
    protected ?string $valueCalc    = null;

    public function __construct() {
        $this->addType('accountId',  'integer');
        $this->addType('externalId', 'integer');
        $this->addType('intervalId', 'integer');
    }
}
