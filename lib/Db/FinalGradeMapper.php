<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FinalGrade>
 */
class FinalGradeMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'besteschule_finalgrades', FinalGrade::class);
    }

    /** @return FinalGrade[] */
    public function findByAccount(int $accountId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)))
            ->orderBy('interval_id', 'ASC')
            ->addOrderBy('subject_name', 'ASC');
        return $this->findEntities($qb);
    }

    public function deleteByAccount(int $accountId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
