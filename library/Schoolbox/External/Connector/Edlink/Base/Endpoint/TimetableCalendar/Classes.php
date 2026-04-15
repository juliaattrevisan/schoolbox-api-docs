<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar;

use Alaress_Date_Format;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\ClassCode;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Lookup\PeopleIds;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Import classes and enrollments from Edlink, saving them as sis_event records
 * with the 'timetable' classification, and sis_class / sis_class_user records.
 *
 * For each class we expand its session date range × its periods × each period's
 * matching weekdays into individual sis_event rows (one per lesson occurrence),
 * with start_at/finish_at constructed from the period's start_time/end_time in
 * the school's local timezone. Classes with no periods or whose periods produce
 * no occurrences in the window are skipped with a warning.
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar
 */
class Classes implements IStrategy
{
    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 500;

    /**
     * Number of records per page
     */
    private const NUM_PER_PAGE = 10000;

    /**
     * ClassEntries we've already created, keyed by class code
     *
     * @var array<string, ClassEntry>
     */
    private array $classEntries = [];

    /**
     * ClassInvolvements we've already created, keyed by classEntryId.externalId
     *
     * @var array<int, array<string, bool>>
     */
    private array $classInvolvements = [];

    /**
     * Session lookup: Edlink session ID => session data (name, start_date, end_date)
     *
     * @var array<string, array>
     */
    private array $sessions = [];

    /**
     * Period lookup: Edlink period ID => period data (name, start_time, end_time, days)
     *
     * @var array<string, array>
     */
    private array $periods = [];

    /**
     * Course lookup: Edlink course ID => course data (name, code)
     *
     * @var array<string, array>
     */
    private array $courses = [];

    /**
     * Class lookup: Edlink class ID => class data, needed to resolve enrollments
     * back to classes.
     *
     * @var array<string, array>
     */
    private array $edlinkClasses = [];

    /**
     * Classes constructor.
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private PeopleIds $peopleIds,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private Alaress_Date_Format $fromDate,
        private Alaress_Date_Format $toDate,
        private \DateTimeZone $timezone
    ) {
    }

    /**
     * @return void
     */
    public function import(): void
    {
        $this->log->info(sprintf(
            'Processing timetables from Edlink between %s and %s.',
            $this->fromDate->getDatabase(),
            $this->toDate->getDatabase()
        ));

        /* Preload sessions for date ranges
         */
        $this->log->info('Preloading sessions.');
        $this->preloadSessions();

        /* Preload courses for class codes
         */
        $this->log->info('Preloading courses.');
        $this->preloadCourses();

        /* Preload periods for bell-schedule slot data
         */
        $this->log->info('Preloading periods.');
        $this->preloadPeriods();

        /* Ensure the PeopleIds lookup is populated. This is a no-op when
         * Profile sync ran earlier and populated the map already.
         */
        $this->log->info('Ensuring Edlink UUID -> external ID map is populated.');
        $this->peopleIds->generate($this->api);

        /* Import classes -> sis_event + sis_class
         */
        $this->log->info('Importing classes.');
        $this->importClasses();

        /* Import enrollments -> sis_class_user
         */
        $this->log->info('Importing enrollments.');
        $this->importEnrollments();
    }

    /**
     * Preload all sessions into a lookup array
     * Sessions provide the date ranges for classes
     */
    private function preloadSessions(): void
    {
        $nextUrl = sprintf('sessions?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $session) {
                $id = trim((string)($session['id'] ?? ''));

                if ('' !== $id) {
                    $this->sessions[$id] = [
                        'name' => trim((string)($session['name'] ?? '')),
                        'start_date' => $session['start_date'] ?? null,
                        'end_date' => $session['end_date'] ?? null,
                    ];
                }
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d sessions.', \count($this->sessions)));
    }

    /**
     * Preload all periods from Edlink into a lookup keyed by period ID.
     *
     * Periods describe the bell-schedule slots (name + time range + days of week).
     */
    private function preloadPeriods(): void
    {
        $nextUrl = sprintf('periods?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $period) {
                $id = trim((string)($period['id'] ?? ''));
                if ('' === $id) {
                    continue;
                }

                $this->periods[$id] = [
                    'name' => trim((string)($period['name'] ?? '')),
                    'start_time' => $period['start_time'] ?? null,
                    'end_time' => $period['end_time'] ?? null,
                    'days' => $period['days'] ?? [],
                ];

                $this->log->debug(sprintf(
                    'Loaded period %s: %s (%s-%s, days=%s)',
                    $id,
                    $this->periods[$id]['name'],
                    $this->periods[$id]['start_time'] ?? 'null',
                    $this->periods[$id]['end_time'] ?? 'null',
                    implode(',', $this->periods[$id]['days'])
                ));
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d periods from Edlink', \count($this->periods)));
    }

    /**
     * Preload all courses into a lookup array
     * Courses provide the class code
     */
    private function preloadCourses(): void
    {
        $nextUrl = sprintf('courses?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $course) {
                $id = trim((string)($course['id'] ?? ''));

                if ('' !== $id) {
                    $this->courses[$id] = [
                        'name' => trim((string)($course['name'] ?? '')),
                        'code' => trim((string)($course['code'] ?? '')),
                    ];
                }
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d courses.', \count($this->courses)));
    }

    /**
     * Import classes from the /classes endpoint
     * Each class becomes a sis_event + sis_class record
     */
    private function importClasses(): void
    {
        $nextUrl = sprintf('classes?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $class) {
                $this->processClass($class);
            }

            $nextUrl = $response['$next'] ?? false;
        }
    }

    /**
     * Process a single class from Edlink
     */
    private function processClass(array $class): void
    {
        $classId = trim((string)($class['id'] ?? ''));
        $className = trim((string)($class['name'] ?? ''));
        $state = trim((string)($class['state'] ?? ''));

        if ('' === $classId || '' === $className) {
            return;
        }

        /* Only import active classes
         */
        if ('active' !== $state) {
            $this->log->debug(sprintf('Skipping inactive class %s (%s)', $className, $classId));
            return;
        }

        /* Derive a class code from the course code, class name, or class ID
         */
        $courseId = trim((string)($class['course_id'] ?? ''));
        $courseCode = '';
        $courseName = '';

        if ('' !== $courseId && isset($this->courses[$courseId])) {
            $courseCode = $this->courses[$courseId]['code'];
            $courseName = $this->courses[$courseId]['name'];
        }

        $classCode = (new ClassCode($this->log))->generate(
            $courseCode ?: $className ?: $classId
        );

        if ('' === $classCode) {
            $this->log->warning(sprintf('Class %s has empty class code - skipping', $className));
            return;
        }

        /* Store for enrollment resolution
         */
        $this->edlinkClasses[$classId] = [
            'name' => $className,
            'code' => $classCode,
            'course_name' => $courseName,
        ];

        /* Resolve session dates for the event, clamped to the configured window
         */
        $startAt = null;
        $finishAt = null;

        foreach ($class['session_ids'] ?? [] as $sessionId) {
            if (!isset($this->sessions[$sessionId])) {
                continue;
            }

            $session = $this->sessions[$sessionId];

            if (null !== $session['start_date']) {
                $sessionStart = $this->parseEdlinkDate($session['start_date']);

                if (null !== $sessionStart && (null === $startAt || $sessionStart->lt($startAt))) {
                    $startAt = $sessionStart;
                }
            }

            if (null !== $session['end_date']) {
                $sessionEnd = $this->parseEdlinkDate($session['end_date']);

                if (null !== $sessionEnd && (null === $finishAt || $sessionEnd->gt($finishAt))) {
                    $finishAt = $sessionEnd;
                }
            }
        }

        /* Skip classes entirely outside the configured window.
         *
         * This check MUST happen before the clamp below, otherwise classes
         * from defunct terms would be clamped into the current window and
         * written as sis_events that don't reflect reality.
         */
        if (null !== $finishAt && $finishAt->lt($this->fromDate)) {
            $this->log->debug(sprintf(
                'Skipping class %s (%s) — ended before window (%s < %s)',
                $className,
                $classCode,
                $finishAt->getDatabase(),
                $this->fromDate->getDatabase()
            ));
            return;
        }

        if (null !== $startAt && $startAt->gt($this->toDate)) {
            $this->log->debug(sprintf(
                'Skipping class %s (%s) — starts after window (%s > %s)',
                $className,
                $classCode,
                $startAt->getDatabase(),
                $this->toDate->getDatabase()
            ));
            return;
        }

        /* Clamp session range into the configured window so we don't generate
         * occurrences outside [fromDate, toDate]. The earlier guard already
         * skipped classes entirely outside the window. */
        $rangeStart = null === $startAt || $startAt->lt($this->fromDate) ? $this->fromDate : $startAt;
        $rangeEnd   = null === $finishAt || $finishAt->gt($this->toDate) ? $this->toDate : $finishAt;

        $periodIds = array_values(array_filter(
            $class['periods'] ?? [],
            fn($p) => '' !== trim((string)$p)
        ));

        if (\count($periodIds) === 0) {
            $this->log->warning(sprintf(
                'Class %s (%s) has no periods — skipping (no occurrences to write)',
                $className,
                $classCode
            ));
            return;
        }

        $occurrences = $this->expandOccurrences($rangeStart, $rangeEnd, $periodIds);

        if (\count($occurrences) === 0) {
            $this->log->warning(sprintf(
                'Class %s (%s) produced 0 occurrences from %d period(s) over %s..%s',
                $className,
                $classCode,
                \count($periodIds),
                $rangeStart->getDatabase(),
                $rangeEnd->getDatabase()
            ));
            return;
        }

        foreach ($occurrences as $occ) {
            /* Per-occurrence guid — must be deterministic so re-imports update
             * existing rows rather than duplicating. */
            $guid = sprintf('%s_%s_%s', $classId, $occ['period_id'], $occ['date']);

            try {
                $event = $this->eventMapper->findOneByColumn('guid', $guid);
            } catch (Exception $e) {
                $event = $this->eventMapper->createModel();
            }

            $event
                ->setTitle($className)
                ->setDetail($courseName ?: null)
                ->setCode($classCode)
                ->setPeriod($occ['period_name'])
                ->setStartAt($occ['start_at'])
                ->setFinishAt($occ['finish_at'])
                ->setClassification(Model::CLASSIFICATION_TIMETABLE)
                ->setGuid($guid)
                ->setSource(Factory::SOURCE_NAME);

            if ($this->perform) {
                $this->eventMapper->save($event);
            }
        }

        $this->log->debug(sprintf(
            'Wrote %d occurrence(s) for class %s (%s)',
            \count($occurrences),
            $className,
            $classCode
        ));

        /* Create sis_class (deduplicate by code)
         */
        if (!isset($this->classEntries[$classCode])) {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($className)
                ->setCode($classCode);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[$classCode] = $classEntry;
        }
    }

    /**
     * Import enrollments from the /enrollments endpoint
     * Each enrollment creates a sis_class_user record
     */
    private function importEnrollments(): void
    {
        $nextUrl = sprintf('enrollments?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $enrollment) {
                $this->processEnrollment($enrollment);
            }

            $nextUrl = $response['$next'] ?? false;
        }
    }

    /**
     * Process a single enrollment
     */
    private function processEnrollment(array $enrollment): void
    {
        $personId = trim((string)($enrollment['person_id'] ?? ''));
        $classId = trim((string)($enrollment['class_id'] ?? ''));
        $state = trim((string)($enrollment['state'] ?? ''));

        if ('' === $personId || '' === $classId) {
            return;
        }

        /* Only import active enrollments
         */
        if ('active' !== $state) {
            return;
        }

        /* Resolve the class to a ClassEntry
         */
        if (!isset($this->edlinkClasses[$classId])) {
            return;
        }

        $classCode = $this->edlinkClasses[$classId]['code'];

        if (!isset($this->classEntries[$classCode])) {
            return;
        }

        $classEntry = $this->classEntries[$classCode];

        /* Resolve person_id (Edlink UUID) to the SIS external ID
         * used by the Profile importer
         */
        $externalId = $this->peopleIds->resolve($personId);

        /* Deduplicate: skip if we've already created this involvement
         */
        $classEntryId = $classEntry->getId();

        if (isset($this->classInvolvements[$classEntryId][$externalId])) {
            return;
        }

        $classInvolvement = $this->classInvolvementMapper->createModel()
            ->setClass($classEntry)
            ->setExternalId($externalId);

        if ($this->perform) {
            $this->classInvolvementMapper->save($classInvolvement);
        }

        $this->classInvolvements[$classEntryId][$externalId] = true;

        $this->log->debug(sprintf(
            'Created enrollment for %s in %s (%s)',
            $externalId,
            $this->edlinkClasses[$classId]['name'],
            $classCode
        ));
    }

    /**
     * Expand a class's session date range into individual lesson occurrences.
     *
     * For each period the class meets, generate one (start_at, finish_at) tuple
     * per matching weekday in the [from, to] range. Times are interpreted in the
     * school's local timezone.
     *
     * @param array<string> $periodIds Period IDs the class meets in
     * @return list<array{start_at: Alaress_Date_Format, finish_at: Alaress_Date_Format, period_id: string, period_name: string, date: string}>
     */
    private function expandOccurrences(
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $periodIds
    ): array {
        return self::expandOccurrencesForTest($from, $to, $periodIds, $this->periods, $this->timezone);
    }

    /**
     * Pure expansion helper — exposed as static for unit testing.
     *
     * @param array<string> $periodIds
     * @param array<string, array{name: string, start_time: ?string, end_time: ?string, days: array<string>}> $periodsLookup
     * @return list<array{start_at: Alaress_Date_Format, finish_at: Alaress_Date_Format, period_id: string, period_name: string, date: string}>
     */
    public static function expandOccurrencesForTest(
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $periodIds,
        array $periodsLookup,
        \DateTimeZone $timezone
    ): array {
        $occurrences = [];

        /* Iterate each calendar day in [from, to] inclusive. */
        $cursor = new \DateTimeImmutable($from->getDatabase(), $timezone);
        $end    = new \DateTimeImmutable($to->getDatabase(), $timezone);

        while ($cursor <= $end) {
            $weekday = strtolower($cursor->format('l')); // "monday", "tuesday", ...
            $dateStr = $cursor->format('Y-m-d');

            foreach ($periodIds as $periodId) {
                if (!isset($periodsLookup[$periodId])) {
                    continue;
                }

                $period = $periodsLookup[$periodId];
                $startTime = $period['start_time'] ?? null;
                $endTime = $period['end_time'] ?? null;

                if (null === $startTime || null === $endTime) {
                    continue;
                }

                /* SPIKE-NOTE: assumes Period.days is an array of lowercase weekday
                 * strings. If the real Edlink payload uses a different shape (e.g.
                 * abbreviations like "Mon", a bitmask, or nested under
                 * meeting_times), adapt this match. */
                if (!\in_array($weekday, $period['days'], true)) {
                    continue;
                }

                $startAt = Alaress_Date_Format::newDatabaseDate(
                    sprintf('%s %s', $dateStr, $startTime),
                    $timezone
                );
                $finishAt = Alaress_Date_Format::newDatabaseDate(
                    sprintf('%s %s', $dateStr, $endTime),
                    $timezone
                );

                $occurrences[] = [
                    'start_at' => $startAt,
                    'finish_at' => $finishAt,
                    'period_id' => $periodId,
                    'period_name' => $period['name'],
                    'date' => $dateStr,
                ];
            }

            $cursor = $cursor->modify('+1 day');
        }

        /* Sort chronologically so callers can rely on order. */
        usort($occurrences, fn($a, $b) => $a['start_at']->getDatabase() <=> $b['start_at']->getDatabase());

        return $occurrences;
    }

    /**
     * Parse an Edlink ISO 8601 date string into an Alaress_Date_Format
     *
     * Edlink sends dates like "2025-09-01T00:00:00.000Z"
     */
    private function parseEdlinkDate(string $dateString): ?Alaress_Date_Format
    {
        if ('' === $dateString) {
            return null;
        }

        try {
            $dateTime = new \DateTimeImmutable($dateString);

            return Alaress_Date_Format::newDatabaseDate(
                $dateTime->format('Y-m-d H:i:s'),
                new \DateTimeZone($dateTime->format('e') !== 'Z' ? $dateTime->format('e') : 'UTC')
            );
        } catch (\Exception $e) {
            $this->log->warning(sprintf('Could not parse Edlink date: %s', $dateString));
            return null;
        }
    }
}
