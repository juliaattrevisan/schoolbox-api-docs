<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar\EDiary;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar\Registry;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;
use Schoolbox\Role;

/**
 * Downloads the eDiary contents for each student
 */
class EDiary implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    protected bool $perform;


    /**
     * @var array
     */
    protected array $api;


    /**
     * @var GenericCollection
     */
    protected GenericCollection $campuses;


    /**
     * @var GenericCollection
     */
    protected GenericCollection $eventTypes;


    /**
     * @var \Schoolbox\External\Profile\Student\Mapper
     */
    private \Schoolbox\External\Profile\Student\Mapper $studentProfileMapper;


    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    protected \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Roletype\Mapper
     */
    protected \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper;


    /**
     * @var \Schoolbox\External\Event\Campus\Mapper
     */
    protected \Schoolbox\External\Event\Campus\Mapper $campusRelationMapper;


    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @var string
     */
    protected string $source = '';


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * Obtain events that started after this datetime
     *
     * @var \Alaress_Date_Format
     */
    protected \Alaress_Date_Format $from;


    /**
     * Obtain events that start before this datetime
     *
     * @var \Alaress_Date_Format
     */
    protected \Alaress_Date_Format $to;


    /**
     * Constructor
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param array                                      $api
     * @param GenericCollection                          $campuses
     * @param GenericCollection                          $eventTypes
     * @param \Schoolbox\External\Profile\Student\Mapper $studentProfileMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper   $staffProfileMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param \Schoolbox\External\Event\Roletype\Mapper  $roletypeRelationMapper
     * @param \Schoolbox\External\Event\Campus\Mapper    $campusRelationMapper
     * @param string                                     $source
     * @param Registry                                   $registry
     * @param \Alaress_Date_Format                       $from
     * @param \Alaress_Date_Format                       $to
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        GenericCollection $campuses,
        GenericCollection $eventTypes,
        \Schoolbox\External\Profile\Student\Mapper $studentProfileMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusRelationMapper,
        string $source,
        Registry $registry,
        \Alaress_Date_Format $from,
        \Alaress_Date_Format $to
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->campuses = $campuses;
        $this->eventTypes = $eventTypes;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->eventMapper = $eventMapper;
        $this->roletypeRelationMapper = $roletypeRelationMapper;
        $this->campusRelationMapper = $campusRelationMapper;

        /* Event source is an arbitrary string that helps identify the thing that imported
         * them into the local event table so you can do a mass remove if needed
         */
        $this->source = $source;

        /* Remember statistics generated procedurally from all the tass products
         * and dump them all at the very end, together - people weren't reading
         * the logs properly and assumed incorrectly when the last product
         * (typically getTeacherNotices) had 0 events in it. People presumed that
         * to be attributable to the entire import which isn't always true.
         *
         * Therefore collect all those notes and put them all together here
         */
        $this->registry = $registry;

        /* Obtain events between these two datetimes
         */
        $this->from = $from;
        $this->to = $to;
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* I think the ediary feed endpoint was supposed to supply the synonymous
         * eventType string associated with each ediary event
         *
         * This algorithmic complexity is about to take a toll on tass
         */
        $feeds = $this->getFeedList('S');

        /* Get a list of Student external ids
         * Use the profile mappers to get a 1D array of those
         * For each person in that set,
         * Hit the endpoint and grab their calendar
         */
        if ($feeds) {
            $userProfiles = $this->studentProfileMapper->findAll();

            foreach ($feeds as $feed) {
                foreach ($userProfiles as $userProfile) {
                    $this->importStudentEvents($userProfile, $feed, 'getStudentEDiary');
                }
            }
        }

        /* I think the ediary feed endpoint was supposed to supply the synonymous
         * eventType string associated with each ediary event
         *
         * This algorithmic complexity is about to take a toll on tass
         */
        $feeds = $this->getFeedList('T');

        if ($feeds) {
            /* Get a list of Staff external ids
             * Use the profile mappers to get a 1D array of those
             *
             * For each person in that set,
             * Hit the endpoint and grab their calendar
             */
            $userProfiles = $this->staffProfileMapper->findAll();

            foreach ($feeds as $feed) {
                foreach ($userProfiles as $userProfile) {
                    $this->importStaffEvents($userProfile, $feed, 'getTeacherEDiary');
                }
            }
        }
    }


    /**
     * Get a list of ediary feeds which categorise all ediary events for everyone
     *
     * @param string $type
     * @return array|null
     */
    private function getFeedList(string $type): ?array
    {
        $endpoint = 'getEDiaryFeeds';

        $this->log->info(sprintf(
            'Accessing endpoint %s with arg %s to get feed list',
            $endpoint,
            $type
        ));

        return $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument(
            $endpoint,
            [
                'type' => $type
            ],
            Api::VERSION_2
        );
    }


    /**
     * Get result sets from an api endpoint
     * Process each of those into SUDS Events, as per tradition,
     * for everyone contained in $users
     *
     * @param \Schoolbox\External\Profile\Student\Model $userProfile
     * @param array                                     $feed
     * @param string                                    $endpoint
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function importStudentEvents(\Schoolbox\External\Profile\Student\Model $userProfile, array $feed, string $endpoint): void
    {
        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $this->log->info(sprintf(
            'Accessing endpoint %s using feed code %s for human with external id %s',
            $endpoint,
            $feed['code'],
            $userProfile->getBaseProfile()->getExternalId()
        ));

        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument(
            $endpoint,
            [
                'studcode' => $userProfile->getBaseProfile()->getExternalId(),
                'feedcode' => $feed['code'],
                'start_date' => $this->from->getUsingFormat('d/m/Y'),
                'end_date' => $this->to->getUsingFormat('d/m/Y')
            ],
            Api::VERSION_2
        );

        $this->importEvents($endpoint, $feed, Role::STUDENT, $tassEvents);
    }


    /**
     * Get result sets from an api endpoint
     * Process each of those into SUDS Events, as per tradition,
     * for everyone contained in $users
     *
     * @param \Schoolbox\External\Profile\Staff\Model $userProfile
     * @param array                                   $feed
     * @param string                                  $endpoint
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function importStaffEvents(\Schoolbox\External\Profile\Staff\Model $userProfile, array $feed, string $endpoint): void
    {
        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $this->log->info(sprintf(
            'Accessing endpoint %s using feed code %s for human with external id %s',
            $endpoint,
            $feed['code'],
            $userProfile->getBaseProfile()->getExternalId()
        ));

        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument(
            $endpoint,
            [
                'tchcode' => $userProfile->getBaseProfile()->getExternalId(),
                'feedcode' => $feed['code'],
                'start_date' => $this->from->getUsingFormat('d/m/Y'),
                'end_date' => $this->to->getUsingFormat('d/m/Y')
            ],
            Api::VERSION_2
        );

        $this->importEvents($endpoint, $feed, Role::STAFF, $tassEvents);
    }


    /**
     * Shank in all the events from the api endpoint into SUDS Events
     *
     * @param string     $endpoint
     * @param array      $feed
     * @param string     $roleType
     * @param array|null $tassEvents
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importEvents(string $endpoint, array $feed, string $roleType, ?array $tassEvents): void
    {
        /* This might be null, or buggy
         * Exit early in that scenario
         */
        if (null === $tassEvents) {
            $this->log->warning(sprintf(
                '%s (%s - %s) returned null; no events found. Skipping',
                $endpoint,
                $this->from->getUsingFormat('d/m/Y'),
                $this->to->getUsingFormat('d/m/Y')
            ));

            return;
        }

        /* A list of missing event types which the admin needs to configure in schoolbox
         */
        $missingEventTypes = [];

        /* Message to log to cli, and also to remember for the summary
         */
        $summaryTass = sprintf(
            'Found %s events at endpoint %s between %s and %s',
            \count($tassEvents['events']),
            $endpoint,
            $this->from->getUsingFormat('d/m/Y'),
            $this->to->getUsingFormat('d/m/Y')
        );

        $this->log->info($summaryTass);

        $n = 0;

        foreach ($tassEvents['events'] as $tassEvent) {
            /* Spawn a new Event instance and ensure that we tell it where it came from
             * Set the normal Event data fields from the $tassEvent structure
             */
            $title = (string)$tassEvent['title'];
            $location = (string)$tassEvent['location'];

            /* TODO There's another field, $tassEvent['summary'], but I can't see how it is different to
             *  $tassEvent['description'] from the api docs, I'd need real data to know which to use
             *  I suspect it's a shorter version of $tassEvent['description']
             */
            $detail = (string)$tassEvent['description'];

            /* Datetimes for the event and whether it's an all-day event
             */
            $startDatetime = \Alaress_Date_Format::newDatabaseDate((string)$tassEvent['start']);
            $endDatetime = \Alaress_Date_Format::newDatabaseDate((string)$tassEvent['end']);

            $allDay = (int)$tassEvent['all_day'];

            /* Third-party source identifier for our import
             *
             * TODO: Potential id conflict
             *  Find out if these ids conflict with ParentCalendar/ParentNotices/StudentCalendar et al.
             *  or if they're somehow unique to eDiary mechanism
             */
            $guid = (string)$tassEvent['id'];

            /* Event Type equivalent
             *
             * These ediary events don't come with a type; I think it's specified
             * by the ediary feed name
             */
            $eventType = $feed['desc'];

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
            $campuses = $this->findCampuses((string)$tassEvent['campus_code']);

            /* Find an event instance that used guid
             */
            try {
                $event = $this->eventMapper->findOneByColumn('guid', $guid);

                $this->log->debug(sprintf(
                    'Found an existing %s event "%s" (schoolbox eventId %s) using guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getId(),
                    $guid
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* Couldn't find any event matching $guid so it's a new event
                 * Make a new source and make a new event instance
                 */
                $event = $this->eventMapper->createModel();

                $this->log->debug(sprintf(
                    'Creating a new %s event "%s" using guid %s',
                    $this->source,
                    $title,
                    $guid
                ));
            }

            /* Assign the usual details
             */
            $event
                ->setTitle($title)
                ->setDetail($detail)
                ->setLocation($location)
                ->setStartAt($startDatetime)
                ->setFinishAt($endDatetime)
                ->setAllday((bool)$allDay)
                ->setType($eventType)
                ->setClassification(\Schoolbox\External\Event\Model::CLASSIFICATION_EVENT)
                ->setGuid($guid)
                ->setSource($this->source);

            /* Assigned Event Type via the payload's Category field "cat_desc"
             */
            if (!isset($this->eventTypes[$eventType])) {
                $this->log->warning(sprintf(
                    '%s event "%s" has an event type "%s" which is not in admin Event Type list; giving it "%s" anyway',
                    $this->source,
                    $event->getTitle(),
                    $eventType,
                    $this->eventTypes->first()->getType()
                ));

                /* Remember it so we can tell the user which event types were missing
                 */
                $missingEventTypes[$eventType] = $eventType;
            }

            /* Some diagnostic loggings
             */
            $this->log->debug(sprintf(
                '%s event "%s" is in campuses: %s',
                $this->source,
                $event->getTitle(),
                implode(', ', $campuses->getPropertyValues('name'))
            ));

            $this->log->debug(sprintf(
                '%s event "%s" is in role type: %s',
                $this->source,
                $event->getTitle(),
                $roleType
            ));

            /* Save it
             */
            if ($this->perform) {
                $this->eventMapper->save($event);

                /* Assign role relations
                 *
                 * These are role-specific and hardcoded per strat, but there might be multiple
                 * instances of the same role type
                 */
                $this->roletypeRelationMapper->save($this->roletypeRelationMapper->createModel()
                    ->setEventId($event->getId())
                    ->setRoleType($roleType));

                $this->log->info(sprintf(
                    'Saved %s event "%s" with roletype relationship %s',
                    $this->source,
                    $event->getTitle(),
                    $roleType
                ));

                /* Slam in all the relevant campuses
                 */
                foreach ($campuses as $campus) {
                    $this->campusRelationMapper->save($this->campusRelationMapper->createModel()
                        ->setEventId($event->getId())
                        ->setCampusName($campus->getName()));

                    $this->log->info(sprintf(
                        'Saved %s event "%s" with campus relationship %s',
                        $this->source,
                        $event->getTitle(),
                        $campus->getName()
                    ));
                }

                $this->log->info(sprintf(
                    'Saved %s event "%s" with guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getGuid()
                ));
            } else {
                $this->log->info(sprintf(
                    'Planning %s event "%s" with guid %s',
                    $this->source,
                    $event->getTitle(),
                    $event->getGuid()
                ));
            }

            /* Increase the count
             */
            $n++;
        }

        /* Tell someone these need to be configured in admin
         */
        $brokenEventTypes = [];

        if (\count($missingEventTypes)) {
            foreach ($missingEventTypes as $missingEventType) {
                $brokenEventTypeMessage = sprintf(
                    'Add this event type in admin: %s',
                    $missingEventType
                );

                $this->log->warning($brokenEventTypeMessage);
                $brokenEventTypes[] = $brokenEventTypeMessage;
            }
        }

        /* Remember for the summary
         */
        $summarySchoolbox = sprintf(
            'Imported %s events into schoolbox',
            $n
        );

        $summaryData = [
            $summaryTass,
            $summarySchoolbox,
            $brokenEventTypes
        ];

        $this->registry->remember($endpoint, $summaryData);
    }


    /**
     * Generate a list of campus ids which might be associated with $campusCode
     *
     * @param string $campusCode
     * @return GenericCollection
     * @throws \UnexpectedValueException
     */
    protected function findCampuses(string $campusCode): GenericCollection
    {
        $campuses = new \Schoolbox\GenericCollection(\Schoolbox\Campus::class);

        foreach ($this->campuses as $campus) {
            if ($campus->getName() === $campusCode || $campus->getExtTimetableCode() === $campusCode) {
                $campuses->add($campus);
            }
        }

        /* If you didn't match any campuses, add them all
         */
        if (!\count($campuses)) {
            return $this->campuses;
        }

        return $campuses;
    }
}
