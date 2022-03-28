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
     * Constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
     * @param Registry                                   $registry
     * @param Mapper                                     $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param string                                     $fromDate
     * @param string                                     $toDate
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        Registry $registry,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        string $fromDate,
        string $toDate
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
        $this->eventMapper = \Schoolbox\External\Event\Mapper::getInstance();
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->overridesMapper = $overridesMapper;
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
                    $data['person']['externalId']
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
                $data['person']['externalId']
            ));

            $this->log->warning($e->getMessage());

            return;
        }

        /* Consume everything inside this payload
         */
        foreach ($payload['data'] as $class) {
            /* Save main event info
             */
            $event = $this->eventMapper->createModel()
                ->setTitle($class['attributes']['className'])
                ->setDetail(null)
                ->setLocation($class['attributes']['roomName'])
                ->setCode($class['attributes']['className'])
                ->setDay($class['attributes']['dayName'])
                ->setPeriod($class['attributes']['periodName'])
                ->setStartAt(\Alaress_Date_Format::newDatabaseDate(sprintf(
                    '%s %s',
                    $class['attributes']['date'],
                    $class['attributes']['startTime']
                )))
                ->setFinishAt(\Alaress_Date_Format::newDatabaseDate(sprintf(
                    '%s %s',
                    $class['attributes']['date'],
                    $class['attributes']['endTime']
                )))
                ->setType($class['attributes']['classType'])
                ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
                ->setGuid($class['id'])
                ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);

            $this->eventMapper->save($event);

            /* Save sis_class record
             */

            /* Save sis_class_user involvements
             */
        }
    }
}