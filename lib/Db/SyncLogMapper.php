<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SyncLogMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'besteschule_sync_logs');
    }

    /**
     * @return SyncLog[]
     */
    public function findByAccount(int $accountId, int $limit = 5): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->tableName)
           ->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)))
           ->orderBy('id', 'DESC')
           ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    public function deleteByAccount(int $accountId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->tableName)
           ->where($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
