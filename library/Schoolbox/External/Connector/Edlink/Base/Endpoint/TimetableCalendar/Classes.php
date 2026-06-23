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
 * For each class we walk Edlink's /days calendar, intersect each in-session
 * day's period_ids with the class's period_ids, and emit one sis_event per
 * match — start_at/finish_at built from the day's date plus the period's
 * wall-clock start/end time in the school's local timezone. Classes with no
 * period_ids or whose period set doesn't intersect any in-session day in the
 * window are skipped with a warning.
 *
 * --------------------------------------------------------------------------
 * Timetable assembly model
 * --------------------------------------------------------------------------
 *
 * A single lesson occurrence is assembled from these Edlink entities:
 *
 *   1. /sessions   — term date range (used only to clamp the class window
 *                    and skip classes entirely outside [fromDate, toDate])
 *   2. /periods    — bell-schedule slot (name + wall-clock start_time/end_time)
 *   3. /days       — the calendar: date + flags[] + period_ids[] (which
 *                    periods run on that date)
 *   4. /classes    — the link: class.session_ids[] for window clamping;
 *                    class.period_ids[] selects which bell slots the class
 *                    is taught in across the calendar
 *
 * expandOccurrences() iterates the preloaded /days lookup, filters to days
 * flagged "in_session" and inside [from, to], intersects day.period_ids with
 * class.period_ids, and emits one sis_event per matching period.
 *
 * --------------------------------------------------------------------------
 * Source SIS notes
 * --------------------------------------------------------------------------
 *
 *  - The fields we read (class.period_ids, period.start_time/end_time, day.date,
 *    day.flags, day.period_ids) are the documented v2.0 Edlink shape. Edlink
 *    historically also exposed class.periods[] as plaintext strings — that
 *    field is documented as deprecated in favour of period_ids and we do not
 *    read it.
 *
 *  - period.start_time / end_time are documented as wall-clock ("Only the
 *    time will be used"). normaliseTimeOfDay() strips the date portion and
 *    trusts the remainder as the school's local wall-clock time. No UTC
 *    conversion is applied.
 *
 *  - day.date arrives as an ISO datetime that may not be midnight in the
 *    school's timezone (e.g. "2023-08-31T05:00:00.000Z" for a Central-Time
 *    school). normaliseDate() re-projects through the school timezone
 *    before formatting as Y-m-d so the calendar date is correct for
 *    schools west of UTC.
 *
 *  - Some source providers (notably PowerSchool, as of probing) do NOT
 *    populate /calendars, /days, /meetings, or class.period_ids via Edlink
 *    at all. For those tenants this importer will produce zero timetable
 *    rows even when the API connection is healthy — the underlying data
 *    isn't surfaced. The "Loaded 0 days from Edlink" log line is the
 *    canonical signal.
 *
 * --------------------------------------------------------------------------
 * Diagnostic log lines worth watching
 * --------------------------------------------------------------------------
 *
 *   - "Loaded N periods from Edlink" with N=0
 *   - "Loaded N days from Edlink" with N=0
 *   - "Class X (Y) has no periods — skipping"
 *   - "Class X (Y) produced 0 occurrences from N period(s) over …"
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
     * How many class involvements to accumulate before flushing them to the
     * database in a single batched transaction.
     */
    private const INVOLVEMENT_BATCH_SIZE = 2000;

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
     * New ClassInvolvement models pending a batched save.
     *
     * @var list<\Schoolbox\External\ClassInvolvement>
     */
    private array $involvementBuffer = [];

    /**
     * Session lookup: Edlink session ID => session data (name, start_date, end_date)
     *
     * @var array<string, array>
     */
    private array $sessions = [];

    /**
     * Period lookup: Edlink period ID => period data (name, start_time, end_time)
     *
     * @var array<string, array>
     */
    private array $periods = [];

    /**
     * Day lookup: Edlink day ID => day data (date, flags, period_ids)
     *
     * @var array<string, array>
     */
    private array $days = [];

    /**
     * Class lookup: Edlink class ID => class data, needed to resolve enrollments
     * back to classes.
     *
     * @var array<string, array>
     */
    private array $edlinkClasses = [];

    /**
     * Crafts minimal sis_event writes (insert new / bump updated_at when
     * unchanged / update only changed columns) to avoid rewriting all seven
     * secondary indexes on every occurrence.
     */
    private EventHandler $eventHandler;

    /**
     * Classes constructor.
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private PeopleIds $peopleIds,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private Alaress_Date_Format $fromDate,
        private Alaress_Date_Format $toDate,
        private \DateTimeZone $timezone
    ) {
        $this->eventHandler = new EventHandler($eventMapper, $log);
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

        /* Preload periods for bell-schedule slot data
         */
        $this->log->info('Preloading periods.');
        $this->preloadPeriods();

        /* Preload days for the calendar (date + in_session flag + which periods run that day)
         */
        $this->log->info('Preloading days.');
        $this->preloadDays();

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
                    'start_time' => self::normaliseTimeOfDay($period['start_time'] ?? null),
                    'end_time' => self::normaliseTimeOfDay($period['end_time'] ?? null),
                ];

                $this->log->debug(sprintf(
                    'Loaded period %s: %s (%s-%s)',
                    $id,
                    $this->periods[$id]['name'],
                    $this->periods[$id]['start_time'] ?? 'null',
                    $this->periods[$id]['end_time'] ?? 'null'
                ));
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d periods from Edlink', \count($this->periods)));
    }

    /**
     * Preload all days from /days into a lookup keyed by day ID.
     *
     * Each day carries the calendar date, a set of flags (e.g. "in_session",
     * "weekend", "holiday") and the list of period IDs that run on that date.
     * The day-driven expansion in expandOccurrences() relies on this lookup to
     * decide when each class meets, replacing the (incorrect) assumption that
     * Period itself carries a days-of-week field.
     */
    private function preloadDays(): void
    {
        $nextUrl = sprintf('days?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $day) {
                $id = trim((string)($day['id'] ?? ''));
                if ('' === $id) {
                    continue;
                }

                $this->days[$id] = [
                    'date' => $day['date'] ?? null,
                    'flags' => $day['flags'] ?? [],
                    'period_ids' => $day['period_ids'] ?? [],
                ];
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d days from Edlink', \count($this->days)));
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

        /* Per-section class code: prefer the class's own sis_id from
         * identifiers[]; fall back to the Edlink class UUID, which is unique
         * and stable ("never conflicts, never changes") so distinct sections
         * never merge — even when the source SIS exposes no sis_id.
         *
         * A class is its own section with its own enrolments, so its identity
         * and label come from the class itself, never the course (which is
         * shared across sibling sections and would collapse them into one).
         */
        $sisId = PeopleIds::resolveIdentifierByType($class, 'sis_id');

        $classCode = (new ClassCode($this->log))->generate($sisId ?: $classId);

        if ('' === $classCode) {
            $this->log->warning(sprintf('Class %s has empty class code - skipping', $className));
            return;
        }

        /* Store for enrollment resolution
         */
        $this->edlinkClasses[$classId] = [
            'name' => $className,
            'code' => $classCode,
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
            $class['period_ids'] ?? [],
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

        /* Per-occurrence guid — deterministic so re-imports update existing rows
         * rather than duplicating. */
        $guids = [];
        foreach ($occurrences as $occ) {
            $guids[] = sprintf('%s_%s_%s', $classId, $occ['period_id'], $occ['date']);
        }

        /* Resolve every existing row for this class in ONE query instead of a
         * SELECT per occurrence, so the diff/insert below is a memory lookup.
         * Only needed when we're actually writing. */
        $existingByGuid = [];
        if ($this->perform) {
            foreach ($this->eventMapper->findByColumn('guid', $guids, ['cache' => false]) as $existing) {
                $existingByGuid[$existing->getGuid()] = $existing;
            }
        }

        /* Split occurrences into brand new rows (cold path → one batched
         * insert) and existing rows (warm path → minimal EventHandler updates).
         * Building is separated from writing so each write phase is a single
         * transaction rather than one commit per row. */
        $newEvents = [];
        $updates = [];

        foreach ($occurrences as $i => $occ) {
            $guid = $guids[$i];
            $oldEvent = $existingByGuid[$guid] ?? null;

            if (null === $oldEvent) {
                $newEvents[] = $this->buildEvent($this->eventMapper->createModel(), $guid, $className, $classCode, $occ);
                continue;
            }

            $updates[] = [$this->buildEvent(clone $oldEvent, $guid, $className, $classCode, $occ), $oldEvent];
        }

        if ($this->perform && \count($updates)) {
            /* Existing rows: EventHandler still writes only the changed columns,
             * but we wrap the whole class's updates in ONE transaction so we pay
             * a single commit instead of one fsync per occurrence. These rows
             * already carry an id, so EventHandler takes its raw-UPDATE path and
             * never opens a nested transaction. */
            $db = $this->eventMapper->getDatabase();
            $db->beginTransaction();
            try {
                foreach ($updates as [$event, $oldEvent]) {
                    $this->eventHandler->save($event, $oldEvent);
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        }

        if ($this->perform && \count($newEvents)) {
            /* One transaction, one reused prepared INSERT, for all new rows. */
            $this->eventMapper->save($newEvents);
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
     * Populate a timetable event model from a single occurrence.
     *
     * Shared by the new-row (bulk insert) and existing-row (EventHandler diff)
     * paths so both write exactly the same field set. Returns the model for
     * fluent collection into a batch.
     *
     * @param array{period_name: string, start_at: Alaress_Date_Format, finish_at: Alaress_Date_Format} $occ
     */
    private function buildEvent(
        Model $event,
        string $guid,
        string $className,
        string $classCode,
        array $occ
    ): Model {
        return $event
            ->setTitle($className)
            ->setDetail(null)
            ->setCode($classCode)
            ->setDay($occ['start_at']->getUsingFormat('l'))
            ->setPeriod($occ['period_name'])
            ->setStartAt($occ['start_at'])
            ->setFinishAt($occ['finish_at'])
            ->setClassification(Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource(Factory::SOURCE_NAME);
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

        /* Flush any involvements still buffered after the last page. */
        $this->flushInvolvements();
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

        /* Mark seen now so duplicates within this run are never buffered. */
        $this->classInvolvements[$classEntryId][$externalId] = true;

        if ($this->perform) {
            $this->involvementBuffer[] = $this->classInvolvementMapper->createModel()
                ->setClass($classEntry)
                ->setExternalId($externalId);

            if (\count($this->involvementBuffer) >= self::INVOLVEMENT_BATCH_SIZE) {
                $this->flushInvolvements();
            }
        }

        $this->log->debug(sprintf(
            'Created enrollment for %s in %s (%s)',
            $externalId,
            $this->edlinkClasses[$classId]['name'],
            $classCode
        ));
    }

    /**
     * Persist any buffered class involvements in a single batched transaction.
     *
     * ClassInvolvements are pure inserts (in-run duplicates are filtered before
     * buffering), so unlike events there is no lookup/diff — just one reused
     * prepared INSERT for the whole chunk.
     */
    private function flushInvolvements(): void
    {
        if (\count($this->involvementBuffer) === 0) {
            return;
        }

        $this->classInvolvementMapper->save($this->involvementBuffer);

        $this->involvementBuffer = [];
    }

    /**
     * Expand a class into individual lesson occurrences using Edlink's /days calendar.
     *
     * Each Day record carries the calendar date, an "in_session" flag, and the
     * list of period IDs that run on that day. A class meets on a given date
     * if and only if the day is in session and some of the day's period IDs
     * overlap the class's period IDs. One occurrence is emitted per matching
     * period; times are taken wall-clock from the period in the school's local
     * timezone.
     *
     * @param array<string> $periodIds Period IDs the class meets in
     * @return list<array{start_at: Alaress_Date_Format, finish_at: Alaress_Date_Format, period_id: string, period_name: string, date: string}>
     */
    private function expandOccurrences(
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $periodIds
    ): array {
        return self::expandOccurrencesForTest(
            $from,
            $to,
            $periodIds,
            $this->periods,
            $this->days,
            $this->timezone
        );
    }

    /**
     * Pure expansion helper — exposed as static for unit testing.
     *
     * @param array<string> $periodIds
     * @param array<string, array{name: string, start_time: ?string, end_time: ?string}> $periodsLookup
     * @param array<string, array{date: ?string, flags: array<string>, period_ids: array<string>}> $daysLookup
     * @return list<array{start_at: Alaress_Date_Format, finish_at: Alaress_Date_Format, period_id: string, period_name: string, date: string}>
     */
    public static function expandOccurrencesForTest(
        Alaress_Date_Format $from,
        Alaress_Date_Format $to,
        array $periodIds,
        array $periodsLookup,
        array $daysLookup,
        \DateTimeZone $timezone
    ): array {
        $occurrences = [];
        $fromDate = substr($from->getDatabase(), 0, 10);
        $toDate   = substr($to->getDatabase(), 0, 10);
        $classPeriods = array_flip($periodIds);

        foreach ($daysLookup as $day) {
            if (!\in_array('in_session', $day['flags'] ?? [], true)) {
                continue;
            }

            $dateStr = self::normaliseDate($day['date'] ?? null, $timezone);
            if (null === $dateStr || $dateStr < $fromDate || $dateStr > $toDate) {
                continue;
            }

            foreach ($day['period_ids'] ?? [] as $periodId) {
                if (!isset($classPeriods[$periodId]) || !isset($periodsLookup[$periodId])) {
                    continue;
                }

                $period = $periodsLookup[$periodId];
                $startTime = $period['start_time'] ?? null;
                $endTime = $period['end_time'] ?? null;

                if (null === $startTime || null === $endTime) {
                    continue;
                }

                $occurrences[] = [
                    'start_at' => Alaress_Date_Format::newDatabaseDate(
                        sprintf('%s %s', $dateStr, $startTime),
                        $timezone
                    ),
                    'finish_at' => Alaress_Date_Format::newDatabaseDate(
                        sprintf('%s %s', $dateStr, $endTime),
                        $timezone
                    ),
                    'period_id' => $periodId,
                    'period_name' => $period['name'] ?? '',
                    'date' => $dateStr,
                ];
            }
        }

        usort($occurrences, fn($a, $b) => $a['start_at']->getDatabase() <=> $b['start_at']->getDatabase());

        return $occurrences;
    }

    /**
     * Normalise an Edlink Day date to a Y-m-d string in school-local time.
     *
     * Edlink encodes Day.date as an ISO datetime anchored at an arbitrary
     * UTC offset (e.g. "2023-08-31T05:00:00.000Z" for a Central-Time school).
     * Re-projecting through the school's timezone before formatting prevents
     * the date from rolling backward for schools west of UTC.
     */
    private static function normaliseDate(?string $value, \DateTimeZone $timezone): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone($timezone)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalise an Edlink period time to a clock-time (H:i:s) in school local time.
     *
     * Edlink returns period times as ISO 8601 datetimes anchored at the Unix
     * epoch with a Z suffix (e.g. "1970-01-01T09:00:00.000Z"). The epoch date
     * is a placeholder and the Z suffix is a Powerschool pass-through — the
     * time-of-day is intended to be interpreted in the school's local
     * timezone. Extract just H:i:s; return null when the input is null,
     * empty, or unparseable.
     */
    private static function normaliseTimeOfDay(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
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

            return Alaress_Date_Format::newFromTimestamp($dateTime->getTimestamp());
        } catch (\Exception $e) {
            $this->log->warning(sprintf('Could not parse Edlink date: %s', $dateString));
            return null;
        }
    }
}
