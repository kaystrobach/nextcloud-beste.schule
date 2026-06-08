<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Service;

use OCA\BesteSchule\Db\Account;
use OCA\BesteSchule\Db\AccountMapper;
use OCA\BesteSchule\Exception\AuthException;
use OCA\BesteSchule\Exception\BesteSchuleException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\Security\ICrypto;

/**
 * Manages beste.schule accounts linked to Nextcloud users.
 *
 * Tokens are stored encrypted via Nextcloud's ICrypto service.
 */
class AccountService
{
    public function __construct(
        private readonly AccountMapper $accountMapper,
        private readonly BesteSchuleService $apiService,
        private readonly ICrypto $crypto,
    ) {
    }

    // ── Public interface ──────────────────────────────────────────────────────

    /** @return Account[] */
    public function getAccountsForUser(string $userId): array
    {
        return $this->accountMapper->findAllByUser($userId);
    }

    /** @return Account[] (admin only) */
    public function getAllAccounts(): array
    {
        return $this->accountMapper->findAll();
    }

    /**
     * Validate the token with the API, discover available students,
     * and persist the account.
     *
     * @param int    $studentId   Pass 0 to auto-select the first student.
     * @param string $calendarUri Nextcloud calendar URI to sync journal into.
     * @throws AuthException|BesteSchuleException
     */
    public function createAccount(
        string $userId,
        string $token,
        int $studentId = 0,
        int $intervalId = 0,
        string $calendarUri = '',
        int $syncInterval = 24,
        string $address = '',
    ): Account {
        // Validate token and discover student
        $students = $this->apiService->students($token);
        if (empty($students)) {
            throw new BesteSchuleException('No students accessible with this token.');
        }

        if ($studentId === 0) {
            $studentId = (int) $students[0]['id'];
        }

        $studentName = '';
        foreach ($students as $s) {
            if ((int) $s['id'] === $studentId) {
                $studentName = trim($s['forename'] . ' ' . $s['name']);
                break;
            }
        }

        $account = new Account();
        $account->setUserId($userId);
        $account->setAccessToken($this->crypto->encrypt($token));
        $account->setStudentId($studentId);
        $account->setStudentName($studentName ?: "Student {$studentId}");
        $account->setIntervalId($intervalId);
        $account->setCalendarUri($calendarUri ?: null);
        $account->setSyncInterval(max(1, $syncInterval));
        $account->setAddress($address ?: null);

        return $this->accountMapper->insert($account);
    }

    public function updateAccount(
        string $userId,
        int $accountId,
        array $fields,
    ): Account {
        $account = $this->accountMapper->findByUserAndId($userId, $accountId);

        if (isset($fields['token'])) {
            // Re-validate new token
            $this->apiService->students($fields['token']);
            $account->setAccessToken($this->crypto->encrypt($fields['token']));
        }
        if (isset($fields['interval_id'])) {
            $account->setIntervalId((int) $fields['interval_id']);
        }
        if (isset($fields['calendar_uri'])) {
            $account->setCalendarUri($fields['calendar_uri'] ?: null);
        }
        if (isset($fields['sync_interval'])) {
            $account->setSyncInterval(max(1, (int) $fields['sync_interval']));
        }
        if (isset($fields['address'])) {
            $account->setAddress($fields['address'] ?: null);
        }

        return $this->accountMapper->update($account);
    }

    public function deleteAccount(string $userId, int $accountId): void
    {
        $account = $this->accountMapper->findByUserAndId($userId, $accountId);
        $this->accountMapper->delete($account);
    }

    /** Admin delete — no user ownership check. */
    public function adminDeleteAccount(int $accountId): void
    {
        $account = $this->accountMapper->findById($accountId);
        $this->accountMapper->delete($account);
    }

    /** Decrypt and return the raw token for an account. */
    public function decryptToken(Account $account): string
    {
        return $this->crypto->decrypt($account->getAccessToken());
    }

    /**
     * Validate a raw token and return available students.
     *
     * @return list<array{id: int, name: string, forename: string}>
     * @throws AuthException|BesteSchuleException
     */
    public function validateToken(string $token): array
    {
        return $this->apiService->students($token);
    }

    public function markSyncSuccess(Account $account): Account
    {
        $account->setLastSyncAt((new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        $account->setLastSyncError(null);
        return $this->accountMapper->update($account);
    }

    public function markSyncError(Account $account, string $error): Account
    {
        $account->setLastSyncAt((new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        $account->setLastSyncError($error);
        return $this->accountMapper->update($account);
    }
}
