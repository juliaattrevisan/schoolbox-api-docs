<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Wonde\Base\Endpoint\IApi;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the classes for a student, saving them as sis_event records
 * with the 'timetable' classification
 */
class Classes implements IStrategy
{

    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 1;


    /**
     * Number of records per page
     * The maximum is an unwritten 200 or so, apparently
     */
    private const NUM_PER_PAGE = 10;


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
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


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
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param IApi                                       $api
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
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;

        /* Temporarily disabled while we wait for feedback from schools
         * $this->overridesMapper = $overridesMapper;
         */

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

        $nextUrl = sprintf(
            'lessons?page=%s&per_page=%s&lessons_start_after=%s&lessons_start_before=%s&include=%s',
            1,
            self::NUM_PER_PAGE,
            $this->fromDate,
            $this->toDate,
            implode(',', [
                'class',
                'employee',
                'period',
                'room'
            ])
        );

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $lessons = $this->api->retrieve('GET', $nextUrl, []);

            foreach ($lessons['data'] as $lesson) {
                /* Save main event info - see if one already exists
                 * if it has the same name, place, day, period and time
                 * If you have five columns the same, you're probably
                 * looking at an event that we've already loaded before
                 */
                $guid = $lesson['id'];

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
                        ->setTitle($lesson['class']['data']['name'])
                        ->setDetail(null)
                        ->setLocation(null)
                        ->setCode($lesson['class']['data']['code'])
                        ->setDay($lesson['period']['data']['day'])
                        ->setPeriod($lesson['period']['data']['name'])
                        ->setStartAt(\Alaress_Date_Format::newDatabaseDate(explode('.', $lesson['start_at']['date'])[0], new \DateTimeZone($lesson['start_at']['timezone'])))
                        ->setFinishAt(\Alaress_Date_Format::newDatabaseDate(explode('.', $lesson['end_at']['date'])[0], new \DateTimeZone($lesson['end_at']['timezone'])))
                        ->setType(null)
                        ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE)
                        ->setGuid($lesson['id'])
                        ->setSource(Prepare::EVENT_IMPORT_IDENTIFIER);
                }

                if ($this->perform) {
                    $this->eventMapper->save($event);
                }

                $this->log->debug(sprintf(
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
                        ->setName($lesson['class']['data']['name'])
                        ->setCode($lesson['class']['data']['code']);

                    if ($this->perform) {
                        $this->classEntryMapper->save($classEntry);
                    }

                    $this->classEntries[$event->getCode()] = $classEntry;
                }

                /* Save sis_class_user involvements
                 * This is the teacher
                 */
                $externalId = $lesson['employee']['data']['mis_id'];
                $name = sprintf(
                    '%s %s %s',
                    $lesson['employee']['data']['title'],
                    $lesson['employee']['data']['forename'],
                    $lesson['employee']['data']['surname']
                );

                $this->saveInvolvement($event, $classEntry, $name, $externalId);

                /* Save sis_class_user involvements
                 * This is the student(s)
                 */
            }

            new \Alaress_Probe($lessons);
        }
    }


    /**
     * Save an involvement for user $externalId, whose name is $name,
     * for the sis_event $eent
     *
     * @param \Schoolbox\External\Event\Model $event
     * @param ClassEntry                      $classEntry
     * @param string                          $name
     * @param string                          $externalId
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function saveInvolvement(
        \Schoolbox\External\Event\Model $event,
        ClassEntry $classEntry,
        string $name,
        string $externalId
    ): void {
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

            $this->log->debug(sprintf(
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
