<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;

/**
 * From a calendar connector configuration, figure out whether the
 * selected Event Type exists; if not, find the first meaningful one
 */
class EventTypeGenerator
{

    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * Constructor.
     */
    public function __construct(\Schoolbox\Calendar\EventTypeMapper $eventTypeMapper)
    {
        $this->eventTypeMapper = $eventTypeMapper;
    }


    /**
     * Find the most meaningful event type for a given config
     *
     * @param \Calendar_Api_Config_Record $config
     * @param array|null                  $allowedEventTypes
     * @return array|null
     */
    public function findEventType(\Calendar_Api_Config_Record $config, ?array $allowedEventTypes): ?array
    {
        // Figure out event type
        $decodedConfig = $config->decodeConfig();
        $eventType     = $decodedConfig->getLuEventType();

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $eventType = $this->eventTypeMapper->findFirstMeaningfulId($eventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return null;
            }
        }

        try {
            /** @var EventType $eventTypeObject */
            $eventTypeObject = $this->eventTypeMapper->findById($eventType);
        } catch (\Schoolbox\Mapper\Exception $e) {
            return null;
        }

        return [
            'color' => $eventTypeObject->getColor(),
            'type' => $eventType
        ];
    }
}
