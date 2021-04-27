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

        /* This comes from an API request at TASS
         */
        $tassEvents = [];

        foreach ($tassEvents as $tassEvent) {

            /* Spawn a new Event instance and ensure that we tell it where it came from
             */
            /** @var Event $event */
            $event = $this->eventMapper->createModel();

            /* Set the normal Event data fields from the $tassEvent structure
             */

            /* Assign campus relations
             *
             * Link it to all the campuses in schoolbox unless you can specifically determine
             * a particular campus relation via "campus_code"
             */
    
            /* Assign role relations
             * These are role-specific and hardcoded per strat
             */
    
            /* Assign Event Type via the payload's Category field "cat_desc"
             */

            /* Set up the Event Source so we know where this came from
             */
            $eventSource = $this->eventSourceMapper->createModel()
                ->setEvent($event)
                ->setSourceName($this->source)
                ->setGuid($tassEvent['id']);

            /* Save it
             */
            if ($this->perform) {
                $this->eventMapper->save($event);
                $this->eventSourceMapper->save($eventSource);

                $this->log->info(sprintf(
                    'Saved event "%s" with event_source.guid %s',
                    $event->getTitle(),
                    $eventSource->getGuid()
                ));
            } else {
                $this->log->info(sprintf(
                    'Planning event "%s" with event_source.guid %s',
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