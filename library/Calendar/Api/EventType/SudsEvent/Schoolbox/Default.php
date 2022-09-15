<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Calendar_Api_EventType_SudsEvent_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{
    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Campus\Mapper
     */
    private \Schoolbox\External\Event\Campus\Mapper $campusMapper;


    /**
     * @var \Schoolbox\External\Event\Roletype\Mapper
     */
    private \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overrideMapper;


    /**
     * @var \Schoolbox\External\Profile\Staff\Mapper
     */
    private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $folderCodes;


    /**
     * @inheritdoc
     */
    public function __construct(
        int $connectorId,
        User $user,
        User $viewer,
        ?GenericCollection $folderCodes,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overrideMapper,
        \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        parent::__construct($connectorId, $user, $viewer);

        $this->folderCodes = $folderCodes;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->eventMapper = $eventMapper;
        $this->campusMapper = $campusMapper;
        $this->roletypeMapper = $roletypeMapper;
        $this->overrideMapper = $overrideMapper;
        $this->staffProfileMapper = $staffProfileMapper;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        $stack = new \Calendar\Event\Collection();

        try {
            /* $eventTypeSettings is now the default event type that we ascribe
             * to events coming out of this subscription when sis_event.type
             * does not match anything in luEventType; we can do that by passing
             * empty array into the generator and doing the $allowedEventTypes
             * check in the iteration below
             */
            $eventTypeSettings = (new \Schoolbox\External\Event\EventTypeGenerator($this->eventTypeMapper))
                ->findEventType($this->loadConnectorConfiguration(), []);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $eventTypeSettings = null;
        }

        /* No event type for the ($config, $allowedEventTypes) combination
         * or something went wrong with the event type detection
         */
        if (null === $eventTypeSettings) {
            return $stack;
        }

        /* The list of events in which $this->getUser() is involved
         */
        $events = (new \Schoolbox\External\Event\EventUsers(
            $this->eventMapper,
            $this->campusMapper,
            $this->roletypeMapper,
            $this->overrideMapper,
            $this->staffProfileMapper
        ))->findForUser(
            $this->getUser(),
            $startTimestamp,
            $endTimestamp,
            $this->folderCodes ?? null
        );

        /* A list of all the event types from which we can choose
         */
        $eventTypes = $this->eventTypeMapper->findAll()->indexBy('type');

        /** @var \Schoolbox\External\Event\Model $event */
        foreach ($events as $event) {
            /* Default colour scheme specified by the calendar subscription
             * admin > calendar > subscribe
             */
            $eventTypeId = $eventTypeSettings['type'];
            $eventTypeColor = $eventTypeSettings['color'];

            /* Find out if this tass event has a corresponding luEventType instance
             * If so, use its colours; otherwise, use the default one specified in
             * the subscription settings: admin > calendar > subscribe
             */
            $eventType = $eventTypes->getOffset($event->getType());

            if ($eventType) {
                $eventTypeId = $eventType->getId();
                $eventTypeColor = $eventType->getColor();
            }

            /* If this derived $eventTypeId was one of the $allowedEventTypes,
             * Let it through; otherwise disregard it
             */
            if (null !== $allowedEventTypes && \count($allowedEventTypes) && !\in_array($eventTypeId, $allowedEventTypes, true)) {
                continue;
            }

            $instance = new \Calendar\Event\Type\SudsEvent\Event();
            $instance->setConnectorId($this->getConnectorId());
            $instance->setTitle($event->getTitle());
            $instance->setDetail($event->getDetail());
            $instance->setLocation($event->getLocation());
            $instance->setCode($event->getCode());
            $instance->setStartTime($event->getStartAt());
            $instance->setEndTime($event->getFinishAt());
            $instance->setAllDay($event->getAllday());
            $instance->setColour($eventTypeColor);
            $instance->setType($eventTypeId);
            $instance->setFolder($event->getFolder());

            $stack->add($instance);
        }

        return $stack;
    }


    /**
     * Find a particular event from this source
     * This implementation of getEvent is actually used
     * (by /www/calendar/ajax/create.php, to display a new event)
     *
     * @param int $eventId
     * @return \Calendar\Event\Collection
     */
    public function getEvent($eventId)
    {
        /* TODO I don't think this is used anymore...
         */
        return new \Calendar\Event\Collection();
    }
}
