<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private string $fromDate;


    /**
     * A date string in the format Y-m-d e.g. 2020-10-12
     *
     * @var string
     */
    private string $toDate;


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
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param IApi                   $api
     * @param Registry               $registry
     * @param Mapper                 $eventMapper
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param string                 $fromDate
     * @param string                 $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        Registry $registry,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
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

        foreach ($this->registry->recallAll() as $externalId => $data) {
            $name = trim("{$data['person']['firstname']} {$data['person']['lastname']}");

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
            if (Profile::TYPE_STUDENT === $data['person']['type']) {
                $endpoint = "timetables/student/{$data['person']['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
            } elseif (Profile::TYPE_STAFF === $data['person']['type']) {
                $endpoint = "timetables/staff/{$data['person']['id']}/timetable-lessons?fromDate={$this->fromDate}&toDate={$this->toDate}";
            } else {
                $this->log->warning(sprintf(
                    'Unknown profile type for %s; skipping timetable preload.',
                    $name
                ));

                continue;
            }

            $this->consumeTimetable($endpoint, $data, $name);
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
        foreach ($payload['data'] as $class) {
            $start = \Alaress_Date_Format::newDatabaseDate(sprintf(
                '%s %s',
                $class['attributes']['date'],
                $class['attributes']['startTime']
            ));

            $finish = \Alaress_Date_Format::newDatabaseDate(sprintf(
                '%s %s',
                $class['attributes']['date'],
                $class['attributes']['endTime']
            ));

            /* Save main event info - see if one already exists
             * if it has the same name, place, day, period and time
             * If you have five columns the same, you're probably
             * looking at an event that we've already loaded before
             */
            $guid = $class['id'];

            /* Try to recycle a previous instance
             */
            if (isset($this->seen[$guid])) {
                $event = $this->seen[$guid];
                $classEntry = $this->classEntries[$event->getCode()];

                $this->log->debug(sprintf(
                    'Reusing previously seen timetable event %s - %s, %s, %s-%s',
                    $event->getTitle(),
                    $event->getDay(),
                    $event->getPeriod(),
                    $event->getStartAt()->getDatabase(),
                    $event->getFinishAt()->getDatabase()
                ));
            } else {
                $event = $this->eventMapper->createModel()
                    ->setTitle(trim((string)$class['attributes']['subject']))
                    ->setDetail(null)
                    ->setLocation(trim((string)$class['attributes']['roomName']))
                    ->setCode(trim((string)$class['attributes']['className']))
                    ->setDay(trim((string)$class['attributes']['dayName']))
                    ->setPeriod(trim((string)$class['attributes']['periodName']))
                    ->setStartAt($start)
                    ->setFinishAt($finish)
                    ->setType(trim((string)$class['attributes']['classType']))
                    ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
                    ->setGuid(trim((string)$class['id']))
                    ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);

                if ($this->perform) {
                    $this->eventMapper->save($event);
                }

                $this->log->info(sprintf(
                    'Creating new timetable event %s - %s, %s, %s-%s',
                    $event->getTitle(),
                    $event->getDay(),
                    $event->getPeriod(),
                    $event->getStartAt()->getDatabase(),
                    $event->getFinishAt()->getDatabase()
                ));

                $this->seen[$guid] = $event;

                /* Save sis_class record
                 */
                if (isset($this->classEntries[$event->getCode()])) {
                    $classEntry = $this->classEntries[$event->getCode()];
                } else {
                    $classEntry = $this->classEntryMapper->createModel()
                        ->setName($class['attributes']['className'])
                        ->setCode($class['attributes']['className']);

                    if ($this->perform) {
                        $this->classEntryMapper->save($classEntry);
                    }

                    $this->classEntries[$event->getCode()] = $classEntry;
                }
            }

            /* Save sis_class_user involvements
             */
            if (isset($this->classInvolvements[$classEntry->getId()][$externalId])) {
                $this->log->debug(sprintf(
                    'Reusing previous class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                    $name,
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
                    'Creating new class involvement for %s (%s) in timetable event %s - %s, %s, %s-%s',
                    $name,
                    $externalId,
                    $event->getTitle(),
                    $event->getDay(),
                    $event->getPeriod(),
                    $event->getStartAt()->getDatabase(),
                    $event->getFinishAt()->getDatabase()
                ));
            }
        }
    }
}
