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
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;


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
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var EventSourceMapper
     */
    private EventSourceMapper $eventSourceMapper;


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
     * @param EventMapper       $eventMapper
     * @param EventSourceMapper $eventSourceMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        EventMapper $eventMapper,
        EventSourceMapper $eventSourceMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;

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
     * Perform the import of these events
     *
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importEvents(): void
    {

        /* Get all the campuses but we can't index them for quick lookup
         * since they are allowed to have the same name and ext_timetable_code
         * via our ui...
         */
        $campuses = CampusMapper::getInstance()->findAll();

        /* TODO Elected event Owner and Author is arbitrarily this person for now...
         */
        $administrator = \Schoolbox\UserMapper::getInstance()->findById(1);

        /* Role id set, in case there are multiple records of the same type
         */
        $roleIds = \Schoolbox\RoleMapper::getInstance()
            ->findByColumn('group_type', \Schoolbox\Role::PARENT)
            ->indexBy('id')
            ->keys();

        /* This comes from an API request at TASS
         */
        $tassEvents = $this->api[DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES]->retrieveDocument('getParentCalendar', [
            'start_date' => '27/04/2021'
        ], Api::VERSION_2);

        foreach ($tassEvents['events'] as $tassEvent) {

            /* Spawn a new Event instance and ensure that we tell it where it came from
             * Set the normal Event data fields from the $tassEvent structure
             */
            $title = $tassEvent['title'];
            $location = $tassEvent['location'];
            $detail = $tassEvent['description'];

            /* Datetimes for the event and whether it's an all-day event
             */
            $startDatetime = \Alaress_Date_Format::newDatabaseDate($tassEvent['start']);
            $endDatetime = \Alaress_Date_Format::newDatabaseDate($tassEvent['start']);
            $allDay = (int)$tassEvent['all_day'];

            /* Third-party source identifier for our import
             */
            $guid = $tassEvent['id'];

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
            $campusIds = [];

            foreach ($campuses as $campus) {
                if ($campus->getName() === $tassEvent['campus_code'] || $campus->getExtTimetableCode() === $tassEvent['campus_code']) {
                    $campusIds[] = $campus->getId();
                }
            }

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
            $event->setRoleCollection([$roleIds]);
    
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