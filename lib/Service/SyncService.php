<?php
declare(strict_types=1);

namespace OCA\BesteSchule\Service;

use OCA\BesteSchule\Db\Account;
use OCA\BesteSchule\Db\AccountMapper;
use OCA\BesteSchule\Db\FinalGrade;
use OCA\BesteSchule\Db\FinalGradeMapper;
use OCA\BesteSchule\Db\Grade;
use OCA\BesteSchule\Db\GradeMapper;
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
        private readonly ICalendarManager   $calendarManager,
        private readonly LoggerInterface    $logger,
    ) {}

    /**
     * Sync one account. Throws on error so the caller can persist the error message.
     */
    public function sync(Account $account): void {
        $token     = $this->accountService->decryptToken($account);
        $studentId = $account->getStudentId();

        $this->logger->info('beste.schule: syncing account {id} (student {sid})', [
            'id'  => $account->getId(),
            'sid' => $studentId,
        ]);

        $this->syncGrades($account, $token, $studentId);
        $this->syncFinalGrades($account, $token, $studentId);

        if ($account->getCalendarUri()) {
            $this->syncCalendar($account, $token, $studentId);
        }
    }

    private function clearCalendarRange(ICalendar $calendar): void {
        $start = (new \DateTime())->sub(new \DateInterval('P' . self::LOOKBACK_DAYS . 'D'));
        $end   = (new \DateTime())->add(new \DateInterval('P' . (self::LOOKAHEAD_WEEKS * 7) . 'D'));

        $existing = $calendar->search('', [], [
            'timerange' => ['start' => $start, 'end' => $end],
        ]);

        foreach ($existing as $obj) {
            foreach ($obj['objects'] as $vevent) {
                $uid = $vevent['UID'][0] ?? null;
                if ($uid && str_starts_with($uid, 'besteschule-')) {
                    try {
                        // @phpstan-ignore-next-line
                        $calendar->deleteObject($uid);
                    } catch (\Exception $e) {
                        $this->logger->error('beste.schule: failed to delete event {uid}: {err}', [
                            'uid' => $uid,
                            'err' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
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

        $this->clearCalendarRange($calendar);

        foreach ($days as $day) {
            $date = $day['date'] ?? null;
            if (!$date) {
                continue;
            }

            // One event per lesson
            foreach ($day['lessons'] ?? [] as $lesson) {
                $this->upsertLessonEvent($calendar, $date, $lesson, $account->getStudentName());
            }

            // All-day events for day notes
            foreach ($day['notes'] ?? [] as $note) {
                $this->upsertNoteEvent($calendar, $date, $note, $account->getStudentName());
            }
        }
    }

    private function upsertLessonEvent(ICalendar $calendar, string $date, array $lesson, string $studentName): void {
        $subject    = $lesson['subject']['name'] ?? 'Stunde';
        $status     = $lesson['status'] ?? 'hold';
        $statusIcon = $this->statusIcon($status);
        $summary    = "{$statusIcon} {$subject}";

        $timeFrom = $lesson['time']['from'] ?? null;
        $timeTo   = $lesson['time']['to']   ?? null;

        $uid = 'besteschule-lesson-' . ($lesson['id'] ?? md5("{$date}-{$subject}"));

        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT', [
            'UID'     => $uid,
            'SUMMARY' => $summary,
        ]);

        if ($timeFrom && $timeTo) {
            $vevent->add('DTSTART', new \DateTime("{$date}T{$timeFrom}:00"));
            $vevent->add('DTEND',   new \DateTime("{$date}T{$timeTo}:00"));
        } else {
            $vevent->add('DTSTART', new \DateTimeImmutable($date));
            $vevent->DTSTART['VALUE'] = 'DATE';
            $vevent->add('DTEND',   new \DateTimeImmutable($date));
            $vevent->DTEND['VALUE'] = 'DATE';
        }

        $desc = $this->buildLessonDescription($lesson);
        if ($desc) {
            $vevent->add('DESCRIPTION', $desc);
        }

        $vevent->add('CATEGORIES', "beste.schule,{$studentName}");

        $calendar->createEvent($uid, $vcal->serialize());
    }

    private function upsertNoteEvent(ICalendar $calendar, string $date, array $note, string $studentName): void {
        $typeName = $note['type']['name'] ?? 'Notiz';
        $text     = $note['description'] ?? '';
        $summary  = "📌 {$typeName}" . ($text ? ": {$text}" : '');

        $uid = 'besteschule-note-' . ($note['id'] ?? md5("{$date}-{$typeName}-{$text}"));

        $vcal = new VCalendar();
        $vevent = $vcal->add('VEVENT', [
            'UID'     => $uid,
            'SUMMARY' => $summary,
        ]);
        $vevent->add('DTSTART', new \DateTimeImmutable($date));
        $vevent->DTSTART['VALUE'] = 'DATE';
        $vevent->add('DTEND',   new \DateTimeImmutable($date));
        $vevent->DTEND['VALUE'] = 'DATE';
        $vevent->add('CATEGORIES', "beste.schule,{$studentName}");

        $calendar->createEvent($uid, $vcal->serialize());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findCalendar(string $userId, string $uri): ?ICalendar {
        $calendars = $this->calendarManager->getCalendarsForPrincipal("principals/users/{$userId}");
        foreach ($calendars as $cal) {
            if ($cal->getUri() === $uri || $cal->getKey() === $uri) {
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
