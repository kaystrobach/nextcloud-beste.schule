<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Service;

use OCA\BesteSchule\Db\Account;
use OCA\BesteSchule\Db\AccountMapper;
use OCA\BesteSchule\Db\FinalGrade;
use OCA\BesteSchule\Db\FinalGradeMapper;
use OCA\BesteSchule\Db\Grade;
use OCA\BesteSchule\Db\GradeMapper;
use OCA\BesteSchule\Db\SyncLog;
use OCA\BesteSchule\Db\SyncLogMapper;
use OCP\Calendar\ICalendar;
use OCP\Calendar\IManager as ICalendarManager;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\UUIDUtil;

/**
 * Orchestrates a full sync for a single beste.schule account:
 *   1. Fetch grades + final grades → upsert into local DB tables
 *   2. Fetch journal weeks → create/update calendar events in Nextcloud
 */
class SyncService {
    private const LOOKBACK_DAYS    = 14;
    private const LOOKAHEAD_WEEKS  = 3;

    public function __construct(
        private readonly BesteSchuleService $api,
        private readonly AccountService     $accountService,
        private readonly GradeMapper        $gradeMapper,
        private readonly FinalGradeMapper   $finalGradeMapper,
        private readonly SyncLogMapper      $syncLogMapper,
        private readonly ICalendarManager   $calendarManager,
        private readonly LoggerInterface    $logger,
    ) {}

    /**
     * Sync one account. Throws on error so the caller can persist the error message.
     */
    public function sync(Account $account): void {
        $token     = $this->accountService->decryptToken($account);
        $studentId = $account->getStudentId();

        $this->addLog($account, 'info', 'Starting synchronization');

        try {
            $this->syncGrades($account, $token, $studentId);
            $this->syncFinalGrades($account, $token, $studentId);

            if ($account->getCalendarUri()) {
                $this->syncCalendar($account, $token, $studentId);
            }

            $this->addLog($account, 'info', 'Synchronization completed successfully');
        } catch (\Exception $e) {
            $this->addLog($account, 'error', 'Synchronization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function addLog(Account $account, string $level, string $message): void {
        $log = new SyncLog();
        $log->setAccountId($account->getId());
        $log->setLevel($level);
        $log->setMessage($message);
        $log->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));
        $this->syncLogMapper->insert($log);
    }


    // ── Grades ────────────────────────────────────────────────────────────────

    private function syncGrades(Account $account, string $token, int $studentId): void {
        $raw = $this->api->grades($token, $studentId);

        // Delete old cached rows and replace with fresh data
        $this->gradeMapper->deleteByAccount($account->getId());

        foreach ($raw as $item) {
            $grade = new Grade();
            $grade->setAccountId($account->getId());
            $grade->setExternalId((int) ($item['id'] ?? 0));
            $grade->setValue((string) ($item['value'] ?? ''));
            $grade->setGivenAt($item['given_at'] ?? null);
            $grade->setSubjectId(isset($item['subject']['id']) ? (int) $item['subject']['id'] : null);
            $grade->setSubjectName((string) ($item['subject']['name'] ?? ''));
            $grade->setCollectionName($item['collection']['name'] ?? null);
            $grade->setTeacherName($this->teacherShort($item['teacher'] ?? []));
            $grade->setWeight(isset($item['weight']) ? (string) $item['weight'] : null);
            $this->gradeMapper->insert($grade);
        }

        $this->logger->debug('beste.schule: synced {n} grades for account {id}', [
            'n'  => count($raw),
            'id' => $account->getId(),
        ]);
    }

    // ── Final grades ──────────────────────────────────────────────────────────

    private function syncFinalGrades(Account $account, string $token, int $studentId): void {
        $raw = $this->api->finalGrades($token, $studentId, $account->getIntervalId());

        $this->finalGradeMapper->deleteByAccount($account->getId());

        foreach ($raw as $item) {
            $fg = new FinalGrade();
            $fg->setAccountId($account->getId());
            $fg->setExternalId((int) ($item['id'] ?? 0));
            $fg->setSubjectName((string) ($item['subject']['name'] ?? ''));
            $fg->setIntervalId(isset($item['interval_id']) ? (int) $item['interval_id'] : null);
            $fg->setIntervalName($item['interval']['name'] ?? null);
            $fg->setValue((string) ($item['value'] ?? ''));
            $fg->setValueCalc($item['value_calc'] ?? null);
            $this->finalGradeMapper->insert($fg);
        }
    }

    // ── Calendar ──────────────────────────────────────────────────────────────

    private function syncCalendar(Account $account, string $token, int $studentId): void {
        $days = $this->api->journalDays($token, $studentId, self::LOOKBACK_DAYS, self::LOOKAHEAD_WEEKS);

        $calendarUri = $account->getCalendarUri();
        $calendar    = $this->findCalendar($account->getUserId(), $calendarUri);

        if ($calendar === null) {
            $this->logger->warning('beste.schule: calendar {uri} not found for user {uid}', [
                'uri' => $calendarUri,
                'uid' => $account->getUserId(),
            ]);
            return;
        }

        $processedUids = [];

        foreach ($days as $day) {
            $date = $day['date'] ?? null;
            if (!$date) {
                continue;
            }

            // One event per lesson
            foreach ($day['lessons'] ?? [] as $lesson) {
                $uid = $this->upsertLessonEvent($calendar, $date, $lesson, $account->getStudentName());
                if ($uid) {
                    $processedUids[] = $uid;
                }
            }

            // All-day events for day notes
            foreach ($day['notes'] ?? [] as $note) {
                $uid = $this->upsertNoteEvent($calendar, $date, $note, $account->getStudentName());
                if ($uid) {
                    $processedUids[] = $uid;
                }
            }
        }

        $this->pruneStaleEvents($calendar, $processedUids);
    }

    private function pruneStaleEvents(ICalendar $calendar, array $processedUids): void {
        // Search for all events in the calendar
        // pattern is empty to find everything, but we can't easily filter by UID in search pattern
        // Nextcloud's search implementation for CalDAV is quite powerful though.
        // We'll fetch events and check their UIDs.
        
        $start = (new \DateTime())->sub(new \DateInterval('P' . self::LOOKBACK_DAYS . 'D'));
        $end   = (new \DateTime())->add(new \DateInterval('P' . (self::LOOKAHEAD_WEEKS * 7) . 'D'));

        $existing = $calendar->search('', [], [
            'timerange' => ['start' => $start, 'end' => $end],
        ]);

        foreach ($existing as $obj) {
            foreach ($obj['objects'] as $vevent) {
                $uid = $vevent['UID'][0] ?? null;
                if ($uid && str_starts_with((string)$uid, 'besteschule-')) {
                    if (!in_array((string)$uid, $processedUids, true)) {
                        $this->logger->debug('beste.schule: pruning stale event {uid}', ['uid' => $uid]);
                        // Since we cannot delete via ICalendar, we mark it as CANCELED
                        $this->cancelEvent($calendar, (string)$uid, $vevent);
                    }
                }
            }
        }
    }

    private function cancelEvent(ICalendar $calendar, string $uid, array $veventData): void {
        $vcal = new VCalendar();
        $summary = $veventData['SUMMARY'][0] ?? 'Entfernt';
        if (!str_contains($summary, '❌')) {
            $summary = '❌ ' . $summary;
        }

        $vevent = $vcal->add('VEVENT', [
            'UID'     => $uid,
            'SUMMARY' => $summary,
            'STATUS'  => 'CANCELLED',
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);

        if (isset($veventData['DTSTART'])) {
            $vevent->add('DTSTART', $veventData['DTSTART'][0]);
            if (isset($veventData['DTSTART']['VALUE'])) {
                $vevent->DTSTART['VALUE'] = $veventData['DTSTART']['VALUE'];
            }
        }
        if (isset($veventData['DTEND'])) {
            $vevent->add('DTEND', $veventData['DTEND'][0]);
            if (isset($veventData['DTEND']['VALUE'])) {
                $vevent->DTEND['VALUE'] = $veventData['DTEND']['VALUE'];
            }
        }

        try {
            $calendar->createFromString($uid . '.ics', $vcal->serialize());
        } catch (\Exception $e) {
            $this->logger->debug('beste.schule: failed to cancel stale event {uid} (might be permission issue or non-writable calendar): ' . $e->getMessage(), ['uid' => $uid]);
        }
    }

    private function upsertLessonEvent(ICalendar $calendar, string $date, array $lesson, string $studentName): string {
        $subject    = $lesson['subject']['name'] ?? 'Stunde';
        $status     = $lesson['status'] ?? 'hold';
        $statusIcon = $this->statusIcon($status);
        $summary    = "{$statusIcon} {$subject}";

        $timeFrom = $lesson['time']['from'] ?? null;
        $timeTo   = $lesson['time']['to']   ?? null;

        $uid = 'besteschule-lesson-' . ($lesson['id'] ?? md5("{$date}-{$subject}-{$timeFrom}-{$timeTo}"));

        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT', [
            'UID'     => $uid,
            'SUMMARY' => $summary,
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);

        if ($timeFrom && $timeTo) {
            $dtStart = new \DateTime("{$date}T{$timeFrom}:00", new \DateTimeZone('Europe/Berlin'));
            $dtEnd   = new \DateTime("{$date}T{$timeTo}:00", new \DateTimeZone('Europe/Berlin'));

            $this->logger->debug('beste.schule: Lesson {subject} on {date} from {from} to {to} (Berlin)', [
                'subject' => $subject,
                'date' => $date,
                'from' => $dtStart->format('Y-m-d H:i:s P'),
                'to' => $dtEnd->format('Y-m-d H:i:s P'),
            ]);

            // Nextcloud expects UTC in VCalendar
            $dtStart->setTimezone(new \DateTimeZone('UTC'));
            $dtEnd->setTimezone(new \DateTimeZone('UTC'));

            $this->logger->debug('beste.schule: Lesson {subject} on {date} from {from} to {to} (UTC)', [
                'subject' => $subject,
                'date' => $date,
                'from' => $dtStart->format('Y-m-d H:i:s P'),
                'to' => $dtEnd->format('Y-m-d H:i:s P'),
            ]);

            $vevent->add('DTSTART', $dtStart);
            $vevent->add('DTEND',   $dtEnd);
        } else {
            $dtStart = new \DateTimeImmutable($date);
            $dtEnd = $dtStart->modify('+1 day');
            $vevent->add('DTSTART', $dtStart);
            $vevent->DTSTART['VALUE'] = 'DATE';
            $vevent->add('DTEND',   $dtEnd);
            $vevent->DTEND['VALUE'] = 'DATE';
        }

        $desc = $this->buildLessonDescription($lesson);
        if ($desc) {
            $vevent->add('DESCRIPTION', $desc);
        }

        $vevent->add('CATEGORIES', "beste.schule,{$studentName}");

        try {
            $calendar->createFromString($uid . '.ics', $vcal->serialize());
        } catch (\Exception $e) {
            $this->logger->debug('beste.schule: lesson failed creation/update for {uid}: ' . $e->getMessage(), ['uid' => $uid]);
            // If it failed due to Conflict, we might want to log it specifically
        }
        return $uid;
    }

    private function upsertNoteEvent(ICalendar $calendar, string $date, array $note, string $studentName): string {
        $typeName = $note['type']['name'] ?? 'Notiz';
        $text     = $note['description'] ?? '';
        $summary  = "📌 {$typeName}" . ($text ? ": {$text}" : '');

        $uid = 'besteschule-note-' . ($note['id'] ?? md5("{$date}-{$typeName}-{$text}"));

        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT', [
            'UID'     => $uid,
            'SUMMARY' => $summary,
            'DTSTAMP' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]);
        $dtStart = new \DateTimeImmutable($date);
        $dtEnd = $dtStart->modify('+1 day');
        $vevent->add('DTSTART', $dtStart);
        $vevent->DTSTART['VALUE'] = 'DATE';
        $vevent->add('DTEND',   $dtEnd);
        $vevent->DTEND['VALUE'] = 'DATE';
        $vevent->add('CATEGORIES', "beste.schule,{$studentName}");

        try {
            $calendar->createFromString($uid . '.ics', $vcal->serialize());
        } catch (\Exception $e) {
            $this->logger->debug('beste.schule: note failed creation/update for {uid}: ' . $e->getMessage(), ['uid' => $uid]);
            // If it failed due to Conflict, we might want to log it specifically
        }
        return $uid;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findCalendar(string $userId, string $uri): ?ICalendar {
        $calendars = $this->calendarManager->getCalendarsForPrincipal("principals/users/{$userId}");
        foreach ($calendars as $cal) {
            if ($cal->getUri() === $uri || $cal->getKey() === $uri || $cal->getDisplayName() === $uri) {
                return $cal;
            }
        }
        return null;
    }

    private function statusIcon(string $status): string {
        return match ($status) {
            'hold'     => '🟢',
            'canceled' => '🔴',
            'planned'  => '🔵',
            default    => '⚪️',
        };
    }

    private function buildLessonDescription(array $lesson): string {
        $parts = [];

        $teachers = array_map(
            fn($t) => trim(($t['forename'] ?? '') . ' ' . ($t['name'] ?? '')),
            $lesson['teachers'] ?? []
        );
        if ($teachers) {
            $parts[] = 'Lehrer: ' . implode(', ', $teachers);
        }

        $rooms = array_map(fn($r) => $r['local_id'] ?? '', $lesson['rooms'] ?? []);
        if ($rooms) {
            $parts[] = 'Raum: ' . implode(', ', array_filter($rooms));
        }

        $notes = array_map(fn($n) => $n['note'] ?? '', $lesson['notes'] ?? []);
        foreach (array_filter($notes) as $n) {
            $parts[] = $n;
        }

        return implode("\n", $parts);
    }

    private function teacherShort(array $teacher): ?string {
        if (empty($teacher)) {
            return null;
        }
        $forename = $teacher['forename'] ?? '';
        $name     = $teacher['name']     ?? '';
        if (!$forename && !$name) {
            return null;
        }
        return trim("{$forename} {$name}");
    }
}
