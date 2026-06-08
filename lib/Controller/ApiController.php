<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Controller;

use OCA\BesteSchule\AppInfo\Application;
use OCA\BesteSchule\Db\FinalGradeMapper;
use OCA\BesteSchule\Db\GradeMapper;
use OCA\BesteSchule\Exception\AuthException;
use OCA\BesteSchule\Exception\BesteSchuleException;
use OCA\BesteSchule\Service\AccountService;
use OCA\BesteSchule\Service\SyncService;
use OCP\AppFramework\Http;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * OCS API controller.
 * All endpoints live under /apps/beste_schule/api/v1/
 */
class ApiController extends OCSController
{
    public function __construct(
        IRequest $request,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
        private readonly AccountService $accountService,
        private readonly SyncService $syncService,
        private readonly GradeMapper $gradeMapper,
        private readonly FinalGradeMapper $finalGradeMapper,
        private readonly ICalendarManager $calendarManager,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    // ── Own-account endpoints ─────────────────────────────────────────────────

    /**
     * Get all accounts for the current user
     *
     * 200: Accounts retrieved successfully
     *
     * @return DataResponse<Http::STATUS_OK, list<array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}>, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getAccounts(): DataResponse
    {
        $userId   = $this->currentUserId();
        $accounts = $this->accountService->getAccountsForUser($userId);
        return new DataResponse($this->serializeAccounts($accounts));
    }

    /**
     * Create a new account for the current user
     *
     * 201: Account created successfully
     *
     * @param string $token The access token from beste.schule
     * @param int $studentId The student ID (0 for auto-select)
     * @param int $intervalId The school year interval ID
     * @param string $calendarUri The Nextcloud calendar URI
     * @param int $syncInterval The sync interval in hours
     * @return DataResponse<Http::STATUS_CREATED, array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string, message: string}, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function createAccount(
        string $token,
        int $studentId = 0,
        int $intervalId = 0,
        string $calendarUri = '',
        int $syncInterval = 24,
    ): DataResponse {
        try {
            $account = $this->accountService->createAccount(
                $this->currentUserId(),
                $token,
                $studentId,
                $intervalId,
                $calendarUri,
                $syncInterval,
            );
            return new DataResponse($this->serializeAccount($account), Http::STATUS_CREATED);
        } catch (AuthException $e) {
            return new DataResponse(['error' => 'invalid_token', 'message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
        } catch (BesteSchuleException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
        }
    }

    /**
     * Update an existing account
     *
     * 200: Account updated successfully
     * 400: Bad request
     *
     * @param int $id The account ID
     * @param array<string, mixed> $data The data to update
     * @return DataResponse<Http::STATUS_OK, array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function updateAccount(int $id, array $data = []): DataResponse
    {
        try {
            $account = $this->accountService->updateAccount($this->currentUserId(), $id, $data);
            return new DataResponse($this->serializeAccount($account));
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Delete an account
     *
     * 200: Account deleted successfully
     * 404: Account not found
     *
     * @param int $id The account ID
     * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteAccount(int $id): DataResponse
    {
        try {
            $this->accountService->deleteAccount($this->currentUserId(), $id);
            return new DataResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Validate a token and return students list
     *
     * 200: Token validated successfully
     *
     * @param string $token The token to validate
     * @return DataResponse<Http::STATUS_OK, array{students: list<array{id: int, name: string, forename: string}>}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: 'invalid_token'}, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function validateToken(string $token): DataResponse
    {
        try {
            $students = $this->accountService->validateToken($token);
            return new DataResponse(['students' => $students]);
        } catch (AuthException $e) {
            return new DataResponse(['error' => 'invalid_token'], Http::STATUS_UNAUTHORIZED);
        } catch (BesteSchuleException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
        }
    }

    /**
     * Trigger a manual sync for one of the current user's accounts
     *
     * 200: Account synced successfully
     * 404: Account not found
     * 500: Sync failed
     *
     * @param int $id The account ID
     * @return DataResponse<Http::STATUS_OK, array{synced: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function syncAccount(int $id): DataResponse
    {
        try {
            $account = $this->accountService->getAccountsForUser($this->currentUserId());
            $found   = null;
            foreach ($account as $a) {
                if ($a->getId() === $id) {
                    $found = $a;
                    break;
                }
            }
            if ($found === null) {
                return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
            }
            $this->syncService->sync($found);
            $this->accountService->markSyncSuccess($found);
            return new DataResponse(['synced' => true]);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List user's calendars
     *
     * 200: Calendars retrieved successfully
     *
     * @return DataResponse<Http::STATUS_OK, list<array{displayname: string, uri: string}>, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getCalendars(): DataResponse
    {
        $userId = $this->currentUserId();
        $calendars = $this->calendarManager->getCalendarsForPrincipal("principals/users/{$userId}");
        $result = [];
        foreach ($calendars as $cal) {
            $result[] = [
                'displayname' => $cal->getDisplayName(),
                'uri' => $cal->getUri(),
            ];
        }
        return new DataResponse($result);
    }

    // ── Grades ────────────────────────────────────────────────────────────────

    /**
     * Get grades for an account
     *
     * 200: Grades retrieved successfully
     *
     * @param int $accountId The account ID (0 for all accounts)
     * @return DataResponse<Http::STATUS_OK, list<array{account: array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}, grades: list<array{id: int, externalId: int, value: string, givenAt: ?string, subjectId: ?int, subjectName: string, collectionName: ?string, teacherName: ?string, weight: ?string}>, average: ?float}>, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getGrades(int $accountId = 0): DataResponse
    {
        $userId   = $this->currentUserId();
        $accounts = $this->accountService->getAccountsForUser($userId);

        $result = [];
        foreach ($accounts as $account) {
            if ($accountId > 0 && $account->getId() !== $accountId) {
                continue;
            }
            $grades = $this->gradeMapper->findByAccount($account->getId());
            $result[] = [
                'account'  => $this->serializeAccount($account),
                'grades'   => array_map(fn($g) => $this->serializeGrade($g), $grades),
                'average'  => $this->computeAverage($grades),
            ];
        }
        return new DataResponse($result);
    }

    /**
     * Get final grades for an account
     *
     * 200: Final grades retrieved successfully
     *
     * @param int $accountId The account ID (0 for all accounts)
     * @return DataResponse<Http::STATUS_OK, list<array{account: array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}, finalgrades: list<array{id: int, externalId: int, subjectName: string, intervalId: int, intervalName: string, value: string, valueCalc: ?string}>}>, array{}>
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getFinalGrades(int $accountId = 0): DataResponse
    {
        $userId   = $this->currentUserId();
        $accounts = $this->accountService->getAccountsForUser($userId);

        $result = [];
        foreach ($accounts as $account) {
            if ($accountId > 0 && $account->getId() !== $accountId) {
                continue;
            }
            $grades = $this->finalGradeMapper->findByAccount($account->getId());
            $result[] = [
                'account'      => $this->serializeAccount($account),
                'finalgrades'  => array_map(fn($g) => $this->serializeFinalGrade($g), $grades),
            ];
        }
        return new DataResponse($result);
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    /**
     * Get all accounts (admin only)
     *
     * 200: All accounts retrieved successfully
     *
     * @return DataResponse<Http::STATUS_OK, list<array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}>, array{}>
     */
    #[NoCSRFRequired]
    public function adminGetAccounts(): DataResponse
    {
        $this->requireAdmin();
        $accounts = $this->accountService->getAllAccounts();
        return new DataResponse($this->serializeAccounts($accounts));
    }

    /**
     * Create an account for any user (admin only)
     *
     * 201: Account created successfully
     *
     * @param string $userId The Nextcloud user ID
     * @param string $token The access token from beste.schule
     * @param int $studentId The student ID (0 for auto-select)
     * @param int $intervalId The school year interval ID
     * @param string $calendarUri The Nextcloud calendar URI
     * @param int $syncInterval The sync interval in hours
     * @return DataResponse<Http::STATUS_CREATED, array{id: int, userId: string, studentId: int, studentName: string, intervalId: int, calendarUri: ?string, syncInterval: int, lastSyncAt: ?string, lastSyncError: ?string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, array{error: string, message: string}, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
     */
    #[NoCSRFRequired]
    public function adminCreateAccount(
        string $userId,
        string $token,
        int $studentId = 0,
        int $intervalId = 0,
        string $calendarUri = '',
        int $syncInterval = 24,
    ): DataResponse {
        $this->requireAdmin();
        try {
            $account = $this->accountService->createAccount(
                $userId,
                $token,
                $studentId,
                $intervalId,
                $calendarUri,
                $syncInterval,
            );
            return new DataResponse($this->serializeAccount($account), Http::STATUS_CREATED);
        } catch (AuthException $e) {
            return new DataResponse(['error' => 'invalid_token', 'message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
        } catch (BesteSchuleException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
        }
    }

    /**
     * Delete any account (admin only)
     *
     * 200: Account deleted successfully
     * 404: Account not found
     *
     * @param int $id The account ID
     * @return DataResponse<Http::STATUS_OK, array{success: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
     */
    #[NoCSRFRequired]
    public function adminDeleteAccount(int $id): DataResponse
    {
        $this->requireAdmin();
        try {
            $this->accountService->adminDeleteAccount($id);
            return new DataResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Trigger a manual sync for any account (admin only)
     *
     * 200: Account synced successfully
     * 404: Account not found
     * 500: Sync failed
     *
     * @param int $id The account ID
     * @return DataResponse<Http::STATUS_OK, array{synced: bool}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
     */
    #[NoCSRFRequired]
    public function adminSyncAccount(int $id): DataResponse
    {
        $this->requireAdmin();
        try {
            $accounts = $this->accountService->getAllAccounts();
            $found    = null;
            foreach ($accounts as $a) {
                if ($a->getId() === $id) {
                    $found = $a;
                    break;
                }
            }
            if ($found === null) {
                return new DataResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
            }
            $this->syncService->sync($found);
            $this->accountService->markSyncSuccess($found);
            return new DataResponse(['synced' => true]);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function currentUserId(): string
    {
        return $this->userSession->getUser()->getUID();
    }

    private function requireAdmin(): void
    {
        if (!$this->groupManager->isAdmin($this->currentUserId())) {
            throw new \RuntimeException('Admin required', Http::STATUS_FORBIDDEN);
        }
    }

    private function serializeAccounts(array $accounts): array
    {
        return array_map(fn($a) => $this->serializeAccount($a), $accounts);
    }

    private function serializeAccount(\OCA\BesteSchule\Db\Account $a): array
    {
        return [
            'id'            => $a->getId(),
            'userId'        => $a->getUserId(),
            'studentId'     => $a->getStudentId(),
            'studentName'   => $a->getStudentName(),
            'intervalId'    => $a->getIntervalId(),
            'calendarUri'   => $a->getCalendarUri(),
            'syncInterval'  => $a->getSyncInterval(),
            'lastSyncAt'    => $a->getLastSyncAt(),
            'lastSyncError' => $a->getLastSyncError(),
        ];
    }

    private function serializeGrade(\OCA\BesteSchule\Db\Grade $g): array
    {
        return [
            'id'             => $g->getId(),
            'externalId'     => $g->getExternalId(),
            'value'          => $g->getValue(),
            'givenAt'        => $g->getGivenAt(),
            'subjectId'      => $g->getSubjectId(),
            'subjectName'    => $g->getSubjectName(),
            'collectionName' => $g->getCollectionName(),
            'teacherName'    => $g->getTeacherName(),
            'weight'         => $g->getWeight(),
        ];
    }

    private function serializeFinalGrade(\OCA\BesteSchule\Db\FinalGrade $g): array
    {
        return [
            'id'           => $g->getId(),
            'externalId'   => $g->getExternalId(),
            'subjectName'  => $g->getSubjectName(),
            'intervalId'   => $g->getIntervalId(),
            'intervalName' => $g->getIntervalName(),
            'value'        => $g->getValue(),
            'valueCalc'    => $g->getValueCalc(),
        ];
    }

    /** @param \OCA\BesteSchule\Db\Grade[] $grades */
    private function computeAverage(array $grades): ?float
    {
        $total = 0.0;
        $count = 0;
        foreach ($grades as $g) {
            $n = $this->parseGradeValue($g->getValue());
            if ($n !== null) {
                $total += $n;
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : null;
    }

    private function parseGradeValue(string $value): ?float
    {
        if (!preg_match('/^([1-6])([+\-]?)(\+\/\-)?$/', trim($value), $m)) {
            return null;
        }
        $base   = (float) $m[1];
        $suffix = $m[2];
        if (isset($m[3])) {
            return $base; // +/- is treated as the base grade
        }
        if ($suffix === '+') {
            return $base - 0.25;
        }
        if ($suffix === '-') {
            return $base + 0.25;
        }
        return $base;
    }
}
