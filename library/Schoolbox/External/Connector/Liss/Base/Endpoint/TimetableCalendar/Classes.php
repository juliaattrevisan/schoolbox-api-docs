<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use PhpXmlRpc\Request;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Helper;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;

/**
 * Class Classes
 *
 * Imports liss events into suds timetable, skipping what we can,
 * applying the defaults as specified in config
 *
 * This is intended to be sandwiched between Prepare and Cleanup
 * strats as per suds tradition. We take a $message as input since
 * the school pushes data to us; we don't retrieve data ourselves
 */
class Classes implements IStrategy
{
    /**
     * This const corresponds to the enum for the config var external_liss_default_event_type
     * This is used to decide if we use numeric teacher ids, or alphanumeric teacher codes
     * If you're not using teacherId, you're presumed to be using teacherCode instead
     */
    private const TEACHER_IDENTIFIER_ID = 'teacherId';


    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * A list of classInvolvements that we saw previously
     *
     * @var array
     */
    private array $classInvolvements = [];


    /**
     * Constructor.
     *
     * @param LoggerInterface                            $log               Logs the things that happened, in a text file
     * @param bool                                       $perform           Since we are receiving a push from schools, we are always in perform mode
     * @param string                                     $defaultType       A string denoting an arbitrary event type that we attribute when the supplied type is non-meaningful
     * @param array                                      $exclusions        A csv list of event types that we should skip
     * @param string                                     $teacherIdentifier Decide whether to use teacherIds or teacherCodes; configured in admin
     * @param string                                     $sourceName
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param Request                                    $message           The data sent to us by the school
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $defaultType,
        private array $exclusions,
        private string $teacherIdentifier,
        private string $sourceName,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private Request $message
    ) {
    }


    /**
     * Import into suds timetable
     *
     * @return void
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Make all the data easier to manipulate by rearranging their guts
         */
        $helper = new Helper($this->log);
        $allDailyData = $helper->reformatAllEvents($this->message->getParam(3)->me['array']);

        /* Keep track of all the problems we encountered
         */
        $skipCount = [
            'noClassCode' => 0,
            'noType' => 0,
            'excludedType' => 0,
            'total' => 0,
        ];

        /* Let's go, trying to skip as many as we can before we try
         * to persist data to the db
         */
        foreach ($allDailyData as $currentEvent) {
            /* Event doesn't have a type; see if we specified one in config
             * If we did, give it that event type instead, then carry onwards
             */
            if ('' === $currentEvent['type']) {
                /* Skip events that don't have a type, because we didn't define
                 * a default event type in config
                 */
                if ('' === $this->defaultType) {
                    $this->log->warning(sprintf(
                        'Skipping "%s" with code "%s" (%s): no type',
                        $currentEvent['name'],
                        $currentEvent['code'],
                        $currentEvent['guid']
                    ));

                    /* Update counts
                     */
                    $skipCount['noType']++;
                    $skipCount['total']++;

                    /* Skip processing this event entirely
                     */
                    continue;
                }

                /* Assign a default one and carry on as if this event
                 * always had this type to start with
                 */
                $currentEvent['type'] = $this->defaultType;

                $this->log->debug(sprintf(
                    'Assigning default event type "%s" to "%s" with code "%s" (%s)',
                    $this->defaultType,
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid']
                ));
            }

            /* Skip events that have a particular type
             * This also applies if you exclude the default one you chose in config
             */
            if (\in_array($currentEvent['type'], $this->exclusions, false)) {
                $this->log->warning(sprintf(
                    'Skipping "%s" with code "%s" (%s): has excluded type "%s"',
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid'],
                    $currentEvent['type']
                ));

                /* Update counts
                 */
                $skipCount['excludedType']++;
                $skipCount['total']++;

                /* Skip processing this event entirely
                 */
                continue;
            }

            /* Skip events that don't have any classCode value
             * This supposedly never happens, but I don't trust
             */
            if ('' === $currentEvent['code']) {
                $this->log->warning(sprintf(
                    'Skipping "%s" with code "%s" (%s): no class codes provided',
                    $currentEvent['name'],
                    $currentEvent['code'],
                    $currentEvent['guid'],
                ));

                /* Update counts
                 */
                $skipCount['noClassCode']++;
                $skipCount['total']++;

                /* Skip processing this event entirely
                 */
                continue;
            }

            /* Process events here
             */
            $this->processClass($currentEvent);
        }

        /* Log diagnostics
         */
        $total = \count($allDailyData);
        $processed = $total - $skipCount['total'];

        $this->log->info('');
        $this->log->info('----------------------------------------------');
        $this->log->info("Total Processed: {$processed}/{$total}");
        $this->log->info("Total Skipped: {$skipCount['total']}/{$total}");
        $this->log->info("Skipped (no class code): {$skipCount['noClassCode']}/{$skipCount['total']}");
        $this->log->info("Skipped (no type): {$skipCount['noType']}/{$skipCount['total']}");
        $this->log->info("Skipped (excluded type): {$skipCount['excludedType']}/{$skipCount['total']}");
        $this->log->info('');
    }


    /**
     * Put this single $currentEvent into the SUDS Timetable
     *
     * @param array $currentEvent
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(array $currentEvent): void
    {
        /* Save sis_event record
         */
        $event = $this->createEvent($currentEvent);

        /* Save sis_class record
         */
        $classEntry = $this->createClassEntry($currentEvent, $event);

        /* Save the involvements
         */
        $this->createInvolvements($currentEvent, $event, $classEntry);
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $currentEvent
     * @return Model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(array $currentEvent): \Schoolbox\External\Event\Model
    {
        /* Fill in these vars as usual
         */
        $classCode = $currentEvent['code'];
        $day = null;
        $detail = $currentEvent['detail'];
        $guid = $currentEvent['guid'];
        $period = $currentEvent['period'];
        $room = $currentEvent['rooms'];
        $title = $currentEvent['name'];

        /* Datetimes for when this class occurs
         */
        $startAt = $currentEvent['startDateTime'];
        $finishAt = $currentEvent['endDateTime'];

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = $currentEvent['type'];

        /* Try to recycle based on guid
         */
        try {
            $event = $this->eventMapper->findOneByColumn('guid', $guid);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $event = $this->eventMapper->createModel();
        }

        $event
            ->setTitle($title)
            ->setDetail($detail)
            ->setLocation($room)
            ->setCode($classCode)
            ->setDay($day)
            ->setPeriod($period)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setType($type)
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource($this->sourceName);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->debug(sprintf(
            'Creating new timetable event %s (%s) on day %s, period %s, %s-%s',
            $event->getTitle(),
            $event->getCode(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $event->getFinishAt()->getDatabase()
        ));

        if ($this->perform) {
            $this->eventMapper->save($event);
        }

        return $event;
    }


    /**
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param array $currentEvent
     * @param Model $event
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $currentEvent, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        /* Constraints in the schema are case insensitive, unlike
         * these array keys - class codes sometimes appear like
         * "Speech Therapy" and "Speech therapy" so inserting them
         * twice will cause it to pack a sad. The classEntries array
         * treats these as unique whereas the db didn't
         */
        $existingKey = strtolower($event->getCode());

        if (isset($this->classEntries[$existingKey])) {
            $classEntry = $this->classEntries[$existingKey];
        } else {
            $classEntry = $this->classEntryMapper->createModel()
                ->setName($currentEvent['name'])
                ->setCode($currentEvent['code']);

            if ($this->perform) {
                $this->classEntryMapper->save($classEntry);
            }

            $this->classEntries[strtolower($event->getCode())] = $classEntry;
        }

        return $classEntry;
    }


    /**
     * Save all the involvements for a given $class
     *
     * For teachers, decide which format of external identifier to use
     * as determined by the config vars
     *
     * @param array      $currentEvent
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(array $currentEvent, \Schoolbox\External\Event\Model $event, ClassEntry $classEntry): void
    {
        if ($this->isTeacherIds()) {
            $staffs = $currentEvent['teacherIds'];
        } else {
            $staffs = $currentEvent['teacherCodes'];
        }

        /* For specific teaching, execute CLEAR_STAFF to disregard the usual guy
         * and add the guy teaching this particular instance (even though they
         * might end up being the same person sometimes)
         *
         * If there's staff, clear them all and put them back in as they're discovered
         */
        if (\count($staffs)) {
            $clearOverride = $this->overridesMapper->createModel()
                ->setEventId($event->getId())
                ->setExternalId(null)
                ->setAction(\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF)
                ->setSequence(1);

            if ($this->perform) {
                $this->overridesMapper->save($clearOverride);
            }
        }

        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         *
         * This applies to all the teachers involved in a class
         */
        foreach ($staffs as $externalId) {
            $this->log->debug(sprintf(
                'Creating new override involvements for %s in timetable event %s - %s, %s, %s-%s',
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));

            $this->saveStaffInvolvement($event, $classEntry, (string)$externalId);
        }

        /* Students are always involved
         */
        foreach ($currentEvent['students'] as $externalId) {
            $this->saveRegularInvolvement($event, $classEntry, (string)$externalId);
        }
    }


    /**
     * Save staff involvements as Overrides
     *
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $externalId
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveStaffInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $externalId
    ): void {
        /* Shove in a regular involvement for the guy who usually teaches this
         */
        $this->saveRegularInvolvement($event, $classEntry, $externalId);

        $involvementOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(2);

        if ($this->perform) {
            $this->overridesMapper->save($involvementOverride);
        }
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $event
     *
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @param string     $externalId
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function saveRegularInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $externalId
    ): void {
        if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
            $this->log->debug(sprintf(
                'Reusing previous class involvement for %s in timetable event %s - %s, %s, %s-%s',
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        } else {
            $classInvolvement = $this->classInvolvementMapper->createModel()
                ->setClass($classEntry)
                ->setExternalId($externalId);

            if ($this->perform) {
                $this->classInvolvementMapper->save($classInvolvement);
            }

            $this->classInvolvements[$classEntry->getId()][$externalId] = true;

            $this->log->debug(sprintf(
                'Creating new class involvement for %s in timetable event %s - %s, %s, %s-%s',
                $externalId,
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        }
    }


    /**
     * Decide if we're going to use teacherIds as the externalId for staff
     * or whether we use teacherCodes instead
     *
     * @return bool
     */
    private function isTeacherIds(): bool
    {
        return self::TEACHER_IDENTIFIER_ID === $this->teacherIdentifier;
    }
}
