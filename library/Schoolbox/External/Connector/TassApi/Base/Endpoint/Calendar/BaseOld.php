<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Event\Source\Mapper as EventSourceMapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\GenericCollection;
use Schoolbox\RoleMapper;
use Schoolbox\User;

/**
 * Class Base
 *
 * All derivatives of this behave in the same manner, just with
 * different endpoints/configs. I'm not sure why they're all identical
 * in Tass, but there's a lot of things about them that I don't understand.
 *
 * Anyway, I though of deploying these functionalities into a Helper class
 * but consciously opted for this instead.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class BaseOld
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
     * This person arbitrarily authored and owns the events
     * (because we need that)
     *
     * But, because events are determined by Role and Campus,
     * the fact that it is "owned" by a particular guy and
     * would appear on that guy's calendar had the events been
     * "Personal" ...doesn't really matter. The events will be
     * visible to anyone in the assigned Role(s) and Campus(es)
     *
     * @var User
     */
    protected User $owner;


    /**
     * @var EventMapper
     */
    protected EventMapper $eventMapper;


    /**
     * @var EventSourceMapper
     */
    protected EventSourceMapper $eventSourceMapper;


    /**
     * @var RoleMapper
     */
    protected RoleMapper $roleMapper;


    /**
     * @var PersonalMapper
     */
    protected PersonalMapper $personalRelationshipMapper;


    /**
     * @var CategoryMapper
     */
    protected CategoryMapper $folderRelationshipMapper;


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
     * Endpoint specific to this event import
     *
     * @var string
     */
    protected string $endpoint = '';


    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @var string
     */
    protected string $source = '';


    /**
     * The RoleType to assign to each event in this import
     *
     * @var string
     */
    protected string $roleType = '';


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * Base constructor.
     *
     * @param LoggerInterface      $log
     * @param bool                 $perform
     * @param array                $api
     * @param GenericCollection    $campuses
     * @param GenericCollection    $eventTypes
     * @param User                 $owner
     * @param EventMapper          $eventMapper
     * @param EventSourceMapper    $eventSourceMapper
     * @param RoleMapper           $roleMapper
     * @param PersonalMapper       $personalRelationshipMapper
     * @param CategoryMapper       $folderRelationshipMapper
     * @param string               $source
     * @param Registry             $registry
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        GenericCollection $campuses,
        GenericCollection $eventTypes,
        User $owner,
        EventMapper $eventMapper,
        EventSourceMapper $eventSourceMapper,
        RoleMapper $roleMapper,
        PersonalMapper $personalRelationshipMapper,
        CategoryMapper $folderRelationshipMapper,
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
        $this->owner = $owner;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
        $this->roleMapper = $roleMapper;

        /* These are relationships to events via event_user and event_folder tables
         */
        $this->personalRelationshipMapper = $personalRelationshipMapper;
        $this->folderRelationshipMapper = $folderRelationshipMapper;

        /* Obtain events between these two datetimes
         */
        $this->from = $from;
        $this->to = $to;

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
    }


    /**
     * Perform the import of these events
     *
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {

        /* A list of missing event types which the admin needs to configure in schoolbox
         */
        $missingEventTypes = [];

        /* Role id collection, in case there are multiple records of the same type
         * e.g. a Student event should be associated with all the Student RoleType roles
         */
        $roleIds = $this->findRoles($this->roleType);

        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $this->log->info(sprintf(
            'Accessing endpoint %s',
            $this->endpoint
        ));

        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument(
            $this->endpoint,
            [
                'start_date' => $this->from->getUsingFormat('d/m/Y'),
                'end_date' => $this->to->getUsingFormat('d/m/Y')
            ],
            Api::VERSION_2
        );

        /* This might be null, or buggy
         * Exit early in that scenario
         */
        if (null === $tassEvents) {
            $this->log->warning(sprintf(
                '%s (%s - %s) returned null; no events found. Skipping',
                $this->endpoint,
                $this->from->getUsingFormat('d/m/Y'),
                $this->to->getUsingFormat('d/m/Y')
            ));

            return;
        }

        /* Message to log to cli, and also to remember for the summary
         */
        $summaryTass = sprintf(
            'Found %s events at endpoint %s between %s and %s',
            \count($tassEvents['events']),
            $this->endpoint,
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
             */
            $guid = (string)$tassEvent['id'];

            /* Event Type equivalent
             */
            $eventType = (string)$tassEvent['cat_desc'];

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
            $campusIds = $this->findCampuses((string)$tassEvent['campus_code']);

            /* Find an event instance that used guid
             */
            try {
                $eventSource = $this->eventSourceMapper->findOneByColumnList([
                    'source_name' => $this->source,
                    'guid' => $guid
                ]);

                $event = $this->eventMapper->findById($eventSource->getEvent()->getId());

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
                $eventSource = $this->eventSourceMapper->createModel();

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
                ->setLocation($location)
                ->setDetail($detail)
                ->setAuthor($this->owner)
                ->setOwner($this->owner)
                ->setAccess(Event::ACCESS_CAMPUS)
                ->setStartDateTime($startDatetime)
                ->setEndDateTime($endDatetime)
                ->setAllDay($allDay)
                ->setCampusCollection($campusIds);

            /* Assign role relations
             *
             * These are role-specific and hardcoded per strat, but there might be multiple
             * instances of the same role type
             */
            $event->setRoleCollection($roleIds);

            /* Assign Event Type via the payload's Category field "cat_desc"
             */
            if (isset($this->eventTypes[$eventType])) {
                $event->setType($this->eventTypes[$eventType]);
            } else {
                $this->log->warning(sprintf(
                    '%s event "%s" has an event type "%s" which is not in admin Event Type list; giving it "%s" anyway',
                    $this->source,
                    $event->getTitle(),
                    $eventType,
                    $this->eventTypes->first()->getType()
                ));

                $event->setType($this->eventTypes->first());

                /* Remember it so we can tell the user which event types were missing
                 */
                $missingEventTypes[$eventType] = $eventType;
            }

            /* Set up the Event Source so we know where this came from
             */
            $eventSource
                ->setEvent($event)
                ->setSourceName($this->source)
                ->setGuid($guid);

            /* Some diagnostic loggings
             */
            $this->log->debug(sprintf(
                '%s event "%s" is in campuses: %s',
                $this->source,
                $event->getTitle(),
                implode(', ', $campusIds)
            ));

            $this->log->debug(sprintf(
                '%s event "%s" is in roles: %s',
                $this->source,
                $event->getTitle(),
                implode(', ', $roleIds)
            ));

            /* Save it
             */
            if ($this->perform) {
                $this->eventMapper->save($event);
                $this->eventSourceMapper->save($eventSource);

                /* Clear event_user and event_folder relationships,
                 * if any existed previously somehow (tass events
                 * are associated with roles and campuses only)
                 *
                 * I know it's a db-in-a-loop, but need to be surgical
                 * cos there's some busted data out in the wild
                 *
                 * SA-13403 explains why, but this deletion may be too heavy-handed.
                 * Nobody is meant to edit the events to change their settings, but they could.
                 */
//                $this->personalRelationshipMapper->deleteByColumn('event_id', $event->getId());
//                $this->folderRelationshipMapper->deleteByColumn('event_id', $event->getId());

                $this->log->info(sprintf(
                    'Saved %s event "%s" with event_source.guid %s',
                    $this->source,
                    $event->getTitle(),
                    $eventSource->getGuid()
                ));
            } else {
                $this->log->info(sprintf(
                    'Planning %s event "%s" with event_source.guid %s',
                    $this->source,
                    $event->getTitle(),
                    $eventSource->getGuid()
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

        $this->registry->remember($this->endpoint, $summaryData);
    }


    /**
     * Generate a list of campus ids which might be associated with $campusCode
     *
     * @param string $campusCode
     * @return array
     */
    protected function findCampuses(string $campusCode): array
    {
        $campusIds = [];

        foreach ($this->campuses as $campus) {
            if ($campus->getName() === $campusCode || $campus->getExtTimetableCode() === $campusCode) {
                $campusIds[] = $campus->getId();
            }
        }

        /* If you didn't match any campuses, add them all
         */
        if (!\count($campusIds)) {
            $campusIds = $this->campuses->getPropertyValues('id');
        }

        return $campusIds;
    }


    /**
     * Find a set of roleIds that are relevant to this import
     *
     * @param string $roleType
     * @return array
     */
    protected function findRoles(string $roleType): array
    {
        return $this->roleMapper
            ->findByColumn('group_type', $roleType)
            ->indexBy('id')
            ->keys();
    }
}
