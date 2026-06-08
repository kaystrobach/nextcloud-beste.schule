<?php
declare(strict_types=1);

namespace OCA\BesteSchule\BackgroundJob;

use OCA\BesteSchule\Db\AccountMapper;
use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Background job that runs every hour and syncs all accounts that are due.
 *
 * Whether an account is "due" is determined by its sync_interval setting
 * compared to last_sync_at. This means we can run the job frequently but
 * each account only actually refreshes at its own configured interval.
 */
class SyncJob extends TimedJob {
    public function __construct(
        ITimeFactory                     $time,
        private readonly AccountMapper   $accountMapper,
        private readonly AccountService  $accountService,
        private readonly SyncService     $syncService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);

        // Check every hour whether any account is due for a sync.
        $this->setInterval(3600);

        // Allow the job to run even if the previous run is still ongoing
        // (each account sync is independent and fast enough).
        $this->setAllowParallelRuns(false);
    }

    protected function run(mixed $argument): void {
        $accounts = $this->accountMapper->findAll();

        foreach ($accounts as $account) {
            if (!$this->isDue($account)) {
                continue;
            }

            try {
                $this->logger->info('beste.schule: background sync for account {id} (user {uid}, student {student})', [
                    'id'      => $account->getId(),
                    'uid'     => $account->getUserId(),
                    'student' => $account->getStudentName(),
                ]);
                $this->syncService->sync($account);
                $this->accountService->markSyncSuccess($account);
            } catch (\Throwable $e) {
                $this->logger->error('beste.schule: sync failed for account {id}: {err}', [
                    'id'  => $account->getId(),
                    'err' => $e->getMessage(),
                ]);
                $this->accountService->markSyncError($account, $e->getMessage());
            }
        }
    }

    private function isDue(\OCA\BesteSchule\Db\Account $account): bool {
        $lastSync = $account->getLastSyncAt();
        if ($lastSync === null) {
            return true;  // Never synced
        }

        $lastSyncTime = new \DateTimeImmutable($lastSync, new \DateTimeZone('UTC'));
        $intervalSecs = $account->getSyncInterval() * 3600;
        $nextSync     = $lastSyncTime->modify("+{$intervalSecs} seconds");
        $now          = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now >= $nextSync;
    }
}
