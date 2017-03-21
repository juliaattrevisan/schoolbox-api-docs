<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\Connector\ExternalEventsFactory;
use Calendar\Event\Collection;
use Calendar\Event\Type\External\Event;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\UserMapper;
use Schoolbox\Calendar\EventType;

class Calendar_Api_EventType_Event_External_Default extends Calendar_Api_EventType_Abstract {

    /**
     * An external event handler instance
     *
     * @var Calendar
     */
    private $externalEvents;

    /**
     * @var EventTypeMapper
     */
    protected $eventTypeMapper;

    /**
     * Constructor
     *
     * @param $user
     * @param $config
     */
    public function __construct($user, $config) {
        $this->setUser($user);
        $this->externalEvents = ExternalEventsFactory::build();
        $this->setEventTypeMapper(EventTypeMapper::getInstance());
    }

    /**
     * Get events from this data source
     *
     * @param int $startTimestamp
     * @param int $endTimestamp
     *
     * @return \Calendar\Event\Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {

        $stack          = new Collection();
        $externalEvents = $this->externalEvents->getExternalEvents($this->getUser(), $startTimestamp, $endTimestamp);

        if($externalEvents) {
            // Figure out event type
            try {
                /** @var Calendar_Api_Config_Record $config */
                $config        = $this->loadConnectorConfiguration();
                $decodedConfig = $config->decodeConfig();
                $eventType     = $decodedConfig->getLuEventType();
            } catch (\Schoolbox\Mapper\Exception $e) {
                $eventType = 1;
            }

            $eventColor = null;
            try {
                /** @var EventType $eventTypeObject */
                $eventTypeObject = $this->getEventTypeMapper()->findById($eventType);
                $eventColor      = $eventTypeObject->getColor();
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }

            foreach ($externalEvents as $externalEvent) {

                $event = new Event();
                $event->setEventId($externalEvent['id']);
                $event->setType($eventType);
                $event->setTitle($externalEvent['title']);
                $event->setStartTime(new Alaress_Date_Format($externalEvent['start'], 'Y-m-d H:i:s'));
                $event->setEndTime(new Alaress_Date_Format($externalEvent['finish'], 'Y-m-d H:i:s'));
                // Publish date is not available in Synergetic Staff Scheduler, but may be available elsewhere
                $event->setPublishDate(empty($externalEvent['publish']) ? '' : new Alaress_Date_Format($externalEvent['finish'], 'Y-m-d H:i:s'));

                //Where is the best place to format this? TODO
                $eventDetail = '';
                if(strlen(strip_tags($externalEvent['comment'])) > 0) {
                    $eventDetail .= strip_tags($externalEvent['comment']);
                }
                if(strlen($externalEvent['team']) > 0) {
                    $eventDetail .= '<br />Team: '.$externalEvent['team'];
                }
                if(strlen($eventDetail) > 0) {
                    $event->setDetail('<p>'.$eventDetail.'</p>');
                }
                $event->setLocation(strlen($externalEvent['location']) > 0 ? $externalEvent['location'] : $externalEvent['room']);

                $event->setEventType('external');
                $event->setAllday(false);

                $owner = UserMapper::getInstance()->findByExternalId($externalEvent['owner']);
                $event->setAuthorId($externalEvent['owner']);
                $event->setAuthorName($owner->formatName());

                $event->setColour($eventColor);

                $event->setConnectorId($this->getConnectorId());

                $stack->add($event);
            }
        }
        return $stack;

    }

    /**
     * Making the abstract class happy
     *
     */
    public function getEvent($eventId) {
        return '';
    }

    /**
     * Helper method for loading this event type's calendar connector config.
     *
     * @return Calendar_Api_Config_Record|object
     */
    public function loadConnectorConfiguration() {
        return Calendar_Api_Config_Mapper::getInstance()->findById(
            $this->getConnectorId()
        );
    }

    /**
     * @param  EventTypeMapper $eventTypeMapper
     * @return $this
     */
    public function setEventTypeMapper($eventTypeMapper) {
        $this->eventTypeMapper = $eventTypeMapper;
        return $this;
    }

    /**
     * @return EventTypeMapper
     */
    public function getEventTypeMapper() {
        return $this->eventTypeMapper;
    }
}
