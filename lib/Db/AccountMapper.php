<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Account>
 */
class AccountMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'besteschule_accounts', Account::class);
    }

    /** @return Account[] */
    public function findAllByUser(string $userId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        return $this->findEntities($qb);
    }

    /** @return Account[] */
    public function findAll(): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());
        return $this->findEntities($qb);
    }

    public function findByUserAndId(string $userId, int $id): Account
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findById(int $id): Account
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /** @return Account[] accounts that are due for sync */
    public function findDueForSync(): array
    {
        $qb = $this->db->getQueryBuilder();
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        // Due = never synced OR last_sync_at + sync_interval hours < now
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->isNull('last_sync_at'),
                    $qb->expr()->lte(
                        $qb->expr()->add(
                            'last_sync_at',
                            $qb->expr()->literal('INTERVAL sync_interval HOUR')
                        ),
                        $qb->createNamedParameter($now)
                    )
                )
            );
        return $this->findEntities($qb);
    }
}
