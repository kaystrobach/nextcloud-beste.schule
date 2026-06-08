<?php

declare(strict_types=1);

namespace OCA\BesteSchule\Service;

use OCA\BesteSchule\Exception\AuthException;
use OCA\BesteSchule\Exception\BesteSchuleException;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the beste.schule REST API.
 */
class BesteSchuleService
{
    private const BASE_URL    = 'https://beste.schule/api';
    private const USER_AGENT  = 'nextcloud-besteschule/1.0';
    private const TIMEOUT     = 30;

    public function __construct(
        private readonly IClientService $clientService,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ── Public API methods ────────────────────────────────────────────────────

    /** Fetch the authenticated user record. Returns the raw associative array. */
    public function me(string $token): array
    {
        return $this->get($token, 'me');
    }

    /**
     * List students accessible via the token.
     *
     * @return array<int, array{id: int, forename: string, name: string, ...}>
     */
    public function students(string $token): array
    {
        return $this->listResource($token, 'students');
    }

    /**
     * Fetch all grade entries for a student.
     *
     * @return list<array>
     */
    public function grades(string $token, int $studentId): array
    {
        return $this->listResource($token, 'grades', [
            'filter' => ['student' => $studentId],
            'include' => 'teacher,collection',
        ]);
    }

    /**
     * Fetch final grades (Endnoten) for a student.
     *
     * @param int $intervalId  Pass 0 to fetch all intervals.
     * @return list<array>
     */
    public function finalGrades(string $token, int $studentId, int $intervalId = 0): array
    {
        $filter = ['student' => $studentId];
        if ($intervalId > 0) {
            $filter['interval'] = $intervalId;
        }
        return $this->listResource($token, 'finalgrades', ['filter' => $filter]);
    }

    /**
     * Fetch journal for a single ISO week (e.g., "2024-19").
     *
     * @return array  The raw week object (contains 'days' key).
     */
    public function journalWeek(string $token, int $studentId, string $isoWeek): array
    {
        return $this->get($token, "journal/weeks/{$isoWeek}", [
            'filter'      => ['student' => $studentId],
            'include'     => 'days.lessons',
            'interpolate' => 'true',
        ]);
    }

    /**
     * Fetch journal for a range of ISO weeks and return all day entries.
     *
     * @return list<array>  Flat list of day objects with nested lessons.
     */
    public function journalDays(string $token, int $studentId, int $lookbackDays, int $lookaheadWeeks): array
    {
        $weeks = $this->computeWeeks($lookbackDays, $lookaheadWeeks);
        $days  = [];

        foreach ($weeks as $week) {
            try {
                $data = $this->journalWeek($token, $studentId, $week);
                foreach ($data['days'] ?? [] as $day) {
                    $days[] = $day;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('beste.schule: failed to fetch week {week}: {err}', [
                    'week' => $week,
                    'err'  => $e->getMessage(),
                ]);
            }
        }

        return $days;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Make a GET request and return the top-level decoded JSON array.
     *
     * @param array<string, mixed> $query  Query parameters (nested 'filter' supported).
     */
    private function get(string $token, string $path, array $query = []): array
    {
        $url     = self::BASE_URL . '/' . ltrim($path, '/');
        $params  = $this->flattenQuery($query);
        $options = [
            'headers' => $this->headers($token),
            'query'   => $params,
            'timeout' => self::TIMEOUT,
        ];

        $this->logger->debug('beste.schule GET {url}', ['url' => $url]);

        try {
            $client   = $this->clientService->newClient();
            $response = $client->get($url, $options);
            $status   = $response->getStatusCode();

            if ($status === 401 || $status === 403) {
                throw new AuthException("Authentication failed (HTTP {$status})");
            }
            if ($status >= 400) {
                $body = substr((string) $response->getBody(), 0, 200);
                throw new BesteSchuleException("API error HTTP {$status}: {$body}");
            }

            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (AuthException | BesteSchuleException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BesteSchuleException("Network error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * GET a list resource and return the `data` array from the response.
     *
     * @return list<array>
     */
    private function listResource(string $token, string $path, array $query = []): array
    {
        $body = $this->get($token, $path, $query);
        return array_values($body['data'] ?? []);
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
            'User-Agent'    => self::USER_AGENT,
        ];
    }

    /**
     * Flatten nested query params: ['filter' => ['student' => 1]] → ['filter[student]' => '1']
     *
     * @return array<string, string>
     */
    private function flattenQuery(array $params, string $prefix = ''): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}[{$key}]" : (string) $key;
            if (is_array($value)) {
                $out = array_merge($out, $this->flattenQuery($value, $fullKey));
            } else {
                $out[$fullKey] = (string) $value;
            }
        }
        return $out;
    }

    /**
     * Compute ISO week strings to fetch based on lookback/lookahead.
     *
     * @return list<string>  e.g. ["2024-19", "2024-20"]
     */
    private function computeWeeks(int $lookbackDays, int $lookaheadWeeks): array
    {
        $weeks = [];
        $now   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Past weeks
        $lookbackWeeks = (int) ceil($lookbackDays / 7);
        for ($i = $lookbackWeeks; $i >= 0; $i--) {
            $dt      = $now->modify("-{$i} weeks");
            $weeks[] = $dt->format('o-W');  // ISO year + week
        }

        // Future weeks
        for ($i = 1; $i <= $lookaheadWeeks; $i++) {
            $dt      = $now->modify("+{$i} weeks");
            $weeks[] = $dt->format('o-W');
        }

        return array_values(array_unique($weeks));
    }
}
