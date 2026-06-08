<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Cached grade entry from beste.schule.
 *
 * @method int     getAccountId()
 * @method void    setAccountId(int $id)
 * @method int     getExternalId()
 * @method void    setExternalId(int $id)
 * @method string  getValue()
 * @method void    setValue(string $v)
 * @method string|null getGivenAt()
 * @method void    setGivenAt(?string $date)
 * @method int|null getSubjectId()
 * @method void    setSubjectId(?int $id)
 * @method string  getSubjectName()
 * @method void    setSubjectName(string $name)
 * @method string|null getCollectionName()
 * @method void    setCollectionName(?string $name)
 * @method string|null getTeacherName()
 * @method void    setTeacherName(?string $name)
 * @method string|null getWeight()
 * @method void    setWeight(?string $w)
 */
class Grade extends Entity {
    protected int     $accountId      = 0;
    protected int     $externalId     = 0;
    protected string  $value          = '';
    protected ?string $givenAt        = null;
    protected ?int    $subjectId      = null;
    protected string  $subjectName    = '';
    protected ?string $collectionName = null;
    protected ?string $teacherName    = null;
    protected ?string $weight         = null;

    public function __construct() {
        $this->addType('accountId',  'integer');
        $this->addType('externalId', 'integer');
        $this->addType('subjectId',  'integer');
    }
}
