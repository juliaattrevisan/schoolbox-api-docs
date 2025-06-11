<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Base\Metadata\Reader;
use Schoolbox\External\Connector\ClassCode;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper\Exception;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Lookup\Users as UserLookup;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{
    /**
     * A list of event guids that we saw previously
     *
     * @var array
     */
    private array $seen = [];


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
     * From and To dates are date string in the format Y-m-d e.g. 2020-10-12
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param UserLookup                                 $userLookup
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param string                                     $fromDate
     * @param string                                     $toDate
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private UserLookup $userLookup,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private string $fromDate,
        private string $toDate
    ) {
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $this->log->info('Processing timetables.');

        /* Spawn a list of people whom we need
         */
        $userLookup = $this->userLookup->generate();

        $metadataReader = new Reader();

        foreach ($userLookup as $externalId => $data) {
            $name = trim("{$data['firstname']} {$data['lastname']}");

            /* Check user's metadata: it might not exist if you tried to sync timetables
             * prior to profiles. This should be rare due to the way schools will have set
             * up their importers, but it could be possible if it was disabled (such as between
             * academic periods/years/holidays), you get the upgrade, then try it out straight
             * away because you're so excited about what I did here. Profiles won't have metadata
             * and this will die immediately.
             */
            if (null === $data['metadata']) {
                $this->log->debug(sprintf(
                    'No timetable metadata for %s (%s) - perhaps user profiles need to be reimported. Skipping',
                    $name,
                    $externalId
                ));

                continue;
            }

            /* If you tried to run a sync but your users (for whatever reason)
             * don't have any metadata, just skip and continue: we can't infer
             * how to obtain timetables otherwise.
             */
            if ('' === trim((string)$data['metadata'])) {
                $this->log->warning(sprintf(
                    '%s (%s) does not have metadata: cannot load timetable data. Skipping',
                    $name,
                    $externalId
                ));

                continue;
            }

            /* Unpack all those ids
             */
            $metadata = $metadataReader->deserialize($data['metadata'] ?? '');

            /* Construct an amalgamation of $data and $metadata, compliant with everything
             * we used to obtain from the Registry instance
             */
            $struct = [
                'person' => [
                    'externalId' => trim((string)$externalId),
                    'firstname' => trim((string)$data['firstname']),
                    'lastname' => trim((string)$data['lastname']),
                    'id' => trim((string)$metadata['id']),
                    'type' => trim((string)$metadata['type']),
                ]
            ];

            /* Decide which endpoint to consume
             *
             * Students get their timetables from a specific place, but also get teacherIds as part
             * of their return payload; we can infer partial staff timetables using this
             *
             * Staff timetables are also arbitrarily retrieved and superimposed on top of whatever
             * was previously derived from student timetables, to get full non-contradictory data
             * (ie. if you're teaching a subject, it's going to be in your timetable - you can't
             * be teaching a subject and having an empty period at the same time). This superposition
             * should theoretically obtain staff classes that don't involve students...
             */
            if (Profile::TYPE_STUDENT === $metadata['type']) {
                $endpoint = "timetables/student/{$metadata['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
            } elseif (Profile::TYPE_STAFF === $metadata['type']) {
                $endpoint = "timetables/staff/{$metadata['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
            } else {
                $this->log->warning(sprintf(
                    'Unknown profile type for %s (%s); skipping timetable preload.',
                    $name,
                    $externalId
                ));

                continue;
            }

             $this->consumeTimetable($endpoint, $struct, $name);
        }
    }


    /**
     * Consume the timetable located at $endpoint, for human $data with name $name
     * Exits early if the person in $data hasn't got a timetable
     *
     * @param string $endpoint
     * @param array  $data
     * @param string $name
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function consumeTimetable(string $endpoint, array $data, string $name): void
    {
        $externalId = $data['person']['externalId'];

        /* Try to fetch and consume this person's timetable
         */
        $this->log->debug(sprintf(
            'Accessing endpoint %s',
            $endpoint
        ));

        /* Some endpoint accesses result in an endpoint saying you didn't provide a thing
         * called a "timeview", i.e. "Missing timeview student information for the given student"
         *
         * This means they don't have any timetable data, but it results in a endpoint error
         * from sentral and an exception being thrown by us. There's no api docs about it,
         * I had to figure it out the hard way
         */
        try {
            $payload = $this->api->retrieveDocument($endpoint);

            $numRecords = \count($payload['data']);

            if ($numRecords) {
                $this->log->debug(sprintf(
                    'Found %s timetableLesson instances for %s %s (sentral id %s, externalId %s)',
                    $numRecords,
                    $data['person']['type'],
                    $name,
                    $data['person']['id'],
                    $externalId
                ));
            }
        } catch (\InvalidArgumentException | \DomainException $e) {
            /* We can't process any timetables in those cases, so log it and continue
             */
            $this->log->warning(sprintf(
                'Could not obtain timetable for %s %s (sentral id %s, externalId %s); they might not have a timetable in sentral.',
                $data['person']['type'],
                $name,
                $data['person']['id'],
                $externalId
            ));

            $this->log->warning($e->getMessage());

            return;
        }

        /* Consume everything inside this payload
         */
        foreach ($payload['data'] as $classInstance) {
            $this->processClass($classInstance, $data);
        }
    }


    /**
     * For each class, process the lessons and apply student/staff involvements
     * Ignore lessons that occur outside the [$from, $to] window
     *
     * @param array $classInstance
     * @param array $data
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function processClass(array $classInstance, array $data): void
    {
        /* Save main event info - see if one already exists
         * if it has the same name, place, day, period and time
         * If you have five columns the same, you're probably
         * looking at an event that we've already loaded before
         */
        $guid = trim((string)$classInstance['id']);

        /* Try to recycle a previous instance
         */
        if (isset($this->seen[$guid])) {
            $event = $this->seen[$guid];

            $this->log->debug(sprintf(
                'Reusing previously seen timetable event %s - %s, %s, %s-%s',
                $event->getTitle(),
                $event->getDay(),
                $event->getPeriod(),
                $event->getStartAt()->getDatabase(),
                $event->getFinishAt()->getDatabase()
            ));
        } else {
            /* Save new sis_event record
             */
            $event = $this->createEvent($classInstance);
        }

        /* Save sis_class record
         */
        $classEntry = $this->createClassEntry($classInstance, $event);

        /* Save the involvements
         */
        $this->createInvolvements($classInstance, $data, $event, $classEntry);
    }


    /**
     * Create a new SUDS Timetable Event with all the class and lesson pieces
     *
     * @param array $classInstance
     * @return Model
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    private function createEvent(array $classInstance): \Schoolbox\External\Event\Model
    {
        /* Fill in these vars as usual
         */
        $classCode = (new ClassCode($this->log))->generate($classInstance['attributes']['className']);
        $day = trim((string)$classInstance['attributes']['dayName']);
        $detail = null;

        /* Arbitrarily concoct a superkey to represent this activity instance
         *
         * Save main event info - see if one already exists
         * if it has the same name, place, day, period and time
         * If you have five columns the same, you're probably
         * looking at an event that we've already loaded before
         */
        $guid = trim((string)$classInstance['id']);

        $period = trim((string)$classInstance['attributes']['periodName']);
        $room = trim((string)$classInstance['attributes']['roomName']);
        $title = trim((string)$classInstance['attributes']['subject']);

        /* Datetimes for when this class occurs
         */
        $allday = !$classInstance['attributes']['startTime'] && !$classInstance['attributes']['endTime'];
        $startAt = \Alaress_Date_Format::newDatabaseDate(sprintf(
            '%s %s',
            $classInstance['attributes']['date'],
            $classInstance['attributes']['startTime'] ?? '00:00:00'
        ));

        $finishAt = \Alaress_Date_Format::newDatabaseDate(sprintf(
            '%s %s',
            $classInstance['attributes']['date'],
            $classInstance['attributes']['endTime'] ?? '00:00:00'
        ));

        /* This is pretty optional - this is the sis' equivalent of our luEventType
         * Sometimes it's provided, sometimes it's not - we can record it here if
         * possible. If you can't get it, dw about it
         */
        $type = trim((string)$classInstance['attributes']['classType']);

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
            ->setAllday($allday)
            ->setPeriod($period)
            ->setStartAt($startAt)
            ->setFinishAt($finishAt)
            ->setType($type)
            ->setMetadata((new Metadata\Builder())->build($classInstance))
            ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
            ->setGuid($guid)
            ->setSource(\Schoolbox\External\Connector\Sentral\Base\Endpoint\Factory::SOURCE_NAME);

        /* Save it and remember what we saw in case we need to reuse this later
         */
        $this->log->info(sprintf(
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

        /* Remember that we saw this
         */
        $this->seen[$guid] = $event;

        return $event;
    }


    /**
     * Create (or reuse) a ClassEntry corresponding to $event
     *
     * @param array $class
     * @param Model $event
     * @return ClassEntry
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createClassEntry(array $class, \Schoolbox\External\Event\Model $event): ClassEntry
    {
        $subjectName = trim((string)$class['attributes']['subject']);
        $classCode = (new ClassCode($this->log))->generate($class['attributes']['className']);

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
                ->setName($subjectName)
                ->setCode($classCode);

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
     * @param array      $class
     * @param array      $data
     * @param Model      $event
     * @param ClassEntry $classEntry
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function createInvolvements(
        array $class,
        array $data,
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry
    ): void {
        $person = $data['person'];

        /* Everyone's always involved in a class, w.r.t. folder involvement
         * but record teachers involvements as overrides for the timetable
         */
        $this->saveRegularInvolvement($event, $classEntry, $person['externalId']);

        /* Staff always involved, for now
         * (Haven't encountered Substitute/Covering teachers scenario yet)
         *
         * Save whoever's teaching this thing on this particular occasion
         */
        if (Profile::TYPE_STAFF === $person['type']) {
            $this->saveStaffInvolvement($event, $classEntry, $person['externalId']);
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
                'Reusing previous regular class involvement for %s in timetable event %s - %s, %s, %s-%s',
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

            $this->log->info(sprintf(
                'Creating new regular class involvement for %s in timetable event %s - %s, %s, %s-%s',
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
     * Save staff involvement as an override, which allows each class
     * to have different teachers depending on their associated sis_event
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

        /* We use the Overrides system to specify a particular person's involvement
         * in a particular sis_event instance, rather than being associated with all
         */
        $this->log->info(sprintf(
            'Creating new Override record for subject %s (%s) on %s %s at %s for %s',
            $event->getTitle(),
            $event->getCode(),
            $event->getDay(),
            $event->getPeriod(),
            $event->getStartAt()->getDatabase(),
            $externalId,
        ));

        /* For specific teaching, execute CLEAR_STAFF to disregard the usual guy
         * and add the guy teaching this particular instance (even though they
         * might end up being the same person sometimes)
         */
        $clearOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId(null)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::CLEAR_STAFF)
            ->setSequence(1);

        $involvementOverride = $this->overridesMapper->createModel()
            ->setEventId($event->getId())
            ->setExternalId($externalId)
            ->setAction(\Schoolbox\External\Event\Overrides\Model::ADD)
            ->setSequence(2);

        if ($this->perform) {
            $this->overridesMapper->save($clearOverride);
            $this->overridesMapper->save($involvementOverride);
        }
    }
}
