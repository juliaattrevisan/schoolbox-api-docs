<?php

/**
 * Calendar_Api_Config_Connector_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Calendar_Api_Config_Connector_Abstract implements Calendar_Api_Config_Connector_Interface
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id = -1;

    /**
     * The label for this connector. Used in the calendar source filter.
     *
     * @var string
     */
    protected $label = '';

    /**
     * The kind of event this configuration is for
     * e.g. 'Event', 'Submission'
     *
     * Public because of Mapper
     *
     * @var string
     */
    protected $eventType = '';

    /**
     * Class name of the API which will use this config
     * e.g. 'Schoolbox', 'Vcal'
     *
     * Public because of Mapper
     *
     * @var string
     */
    protected $connectorClass = '';

    /**
     * Corresponds to the luEventType table
     *
     * @var string
     */
    protected $luEventType = '1';

    /**
     * Whether or not the current user can view these events
     * Setting this to False prevents event accumulation
     *
     * @var bool
     */
    protected $hasPermissions = false;


    /**
     * Gets a sensible default label for this connector.
     *
     * @return string
     */
    abstract public function getDefaultLabel();

    /**
     * Render event type lookup selector with the currently chosen
     * value preselected
     *
     * @param SitePage $page
     * @param int $preselected
     */
    protected function renderEventType($page, $preselected)
    {
        $eventHandler = new Calendar();

        $allLookupEventTypes = $eventHandler->getTypeList();

        if (is_array($allLookupEventTypes) && count($allLookupEventTypes)) {
            foreach ($allLookupEventTypes as $currentLookupEventType) {
                if ($currentLookupEventType['eventTypeID'] == $preselected) {
                    $selected = 'selected="selected"';
                } else {
                    $selected = '';
                }

                $page->setVar(
                    array(
                        'LUEVENTTYPE_VALUE'    => $currentLookupEventType['eventTypeID'],
                        'LUEVENTTYPE_NAME'     => Scrub::text($currentLookupEventType['eventType']),
                        'LUEVENTTYPE_SELECTED' => $selected,
                    )
                );

                $page->parse('luEventType_block', 'luEventType', true);
            }
            $page->parse('event_type_list_block', 'event_type_list');
        } else {
            $page->setVar('luEventType_block', '');
        }
    }


    /* Getters/Setters
     */

    /**
     * Set event type
     *
     * @param string $eventType
     */
    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * Get event type
     *
     * @return string
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * Set connector class
     *
     * @param string $connectorClass
     */
    public function setConnectorClass($connectorClass)
    {
        $this->connectorClass = $connectorClass;
    }

    /**
     * Get connector class
     *
     * @return string
     */
    public function getConnectorClass()
    {
        return $this->connectorClass;
    }

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set luEventType
     *
     * @param string $luEventType
     */
    public function setLuEventType($luEventType)
    {
        $this->luEventType = $luEventType;
    }

    /**
     * Get luEventType
     *
     * @return string
     */
    public function getLuEventType()
    {
        return $this->luEventType;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param  string $label
     *
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }
}
