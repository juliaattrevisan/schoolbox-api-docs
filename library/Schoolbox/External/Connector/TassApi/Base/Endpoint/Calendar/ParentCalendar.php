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
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\RoleMapper;


/**
 * Class ParentCalendar
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class ParentCalendar implements IStrategy
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
     * @var array
     */
    private array $api;


    /**
     * @var GenericCollection
     */
    private GenericCollection $campuses;


    /**
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var EventSourceMapper
     */
    private EventSourceMapper $eventSourceMapper;


    /**
     * @var RoleMapper
     */
    private RoleMapper $roleMapper;


    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @var string
     */
    private string $source;


    /**
     * ParentCalendar constructor.
     *
     * @param LoggerInterface   $log
     * @param bool              $perform
     * @param array             $api
     * @param GenericCollection $campuses
     * @param EventMapper       $eventMapper
     * @param EventSourceMapper $eventSourceMapper
     * @param RoleMapper        $roleMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        GenericCollection $campuses,
        EventMapper $eventMapper,
        EventSourceMapper $eventSourceMapper,
        RoleMapper $roleMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->campuses = $campuses;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
        $this->roleMapper = $roleMapper;

        $this->source = self::class;
    }


    /**
     * Purge existing events so we don't have to worry about diffing their
     * relationships and stuff. It's going to be wrapped in a transaction
     * anyway, so if anything dies we roll back.
     *
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function prepare(): void
    {
        $this->eventSourceMapper->deleteEventsFromSource($this->source);
    }


    /**
     * Generate a list of campus ids which might be associated with $campusCode
     *
     * @param string $campusCode
     * @return array
     */
    private function findCampuses(string $campusCode): array
    {
        $campusIds = [];

        foreach ($this->campuses as $campus) {
            if ($campus->getName() === $campusCode || $campus->getExtTimetableCode() === $campusCode) {
                $campusIds[] = $campus->getId();
            }
        }

        return $campusIds;
    }


    /**
     * Find a set of roleIds that are relevant to this import
     *
     * @param string $roleType
     * @return array
     */
    private function findRoles(string $roleType): array
    {
        return $this->roleMapper
            ->findByColumn('group_type', $roleType)
            ->indexBy('id')
            ->keys();
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
    private function importEvents(): void
    {

        /* TODO Elected event Owner and Author is arbitrarily this person for now...
         */
        $administrator = \Schoolbox\UserMapper::getInstance()->findById(1);

        /* Role id collection, in case there are multiple records of the same type
         * e.g. a Student event should be associated with all the Student Roletype roles
         */
        $roleIds = $this->findRoles(Role::PARENT);

        /* This comes from an API request at TASS
         * We want events bracketed by 1 month in the past and 12 months in the future
         */
        $earliest = \Alaress_Date_Format::now()->addMonths(-1);
        $latest = \Alaress_Date_Format::now()->addMonths(12);

        /* Grab 'em and shank 'em
         */
        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument('getParentCalendar', [
            'start_date' => $earliest->getUsingFormat('d/m/Y'),
            'end_date' => $latest->getUsingFormat('d/m/Y')
        ], Api::VERSION_2);

        foreach ($tassEvents['events'] as $tassEvent) {

            /* Spawn a new Event instance and ensure that we tell it where it came from
             * Set the normal Event data fields from the $tassEvent structure
             */
            $title = $tassEvent['title'];
            $location = $tassEvent['location'];

            /* TODO There's another field, $tassEvent['summary'], but I can't see how it is different to
             *  $tassEvent['description'] from the api docs, I'd need real data to know which to use
             */
            $detail = $tassEvent['description'];

            /* Datetimes for the event and whether it's an all-day event
             */
            $startDatetime = \Alaress_Date_Format::newDatabaseDate($tassEvent['start']);
            $endDatetime = \Alaress_Date_Format::newDatabaseDate($tassEvent['end']);
            $allDay = (int)$tassEvent['all_day'];

            /* Third-party source identifier for our import
             */
            $guid = $tassEvent['id'];

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
            $campusIds = $this->findCampuses((string)$tassEvent['campus_code']);

            /** @var Event $event */
            $event = $this->eventMapper->createModel()
                ->setTitle($title)
                ->setLocation($location)
                ->setDetail($detail)
                ->setAuthor($administrator)
                ->setOwner($administrator)
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

            /* Set up the Event Source so we know where this came from
             */
            $eventSource = $this->eventSourceMapper->createModel()
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
                '%s event "%s" is in rolees: %s',
                $this->source,
                $event->getTitle(),
                implode(', ', $roleIds)
            ));

            /* Save it
             */
            if ($this->perform) {
                $this->eventMapper->save($event);
                $this->eventSourceMapper->save($eventSource);

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
        }
    }


    /**
     * Import events from this source
     *
     * Facade method for Prepare/Import/Cleanup
     */
    public function import(): void
    {
        $this->prepare();
        $this->importEvents();
    }
}