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
    protected int $id = -1;

    /**
     * The label for this connector. Used in the calendar source filter.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The kind of event this configuration is for
     * e.g. 'Event', 'Submission'
     *
     * Public because of Mapper
     *
     * @var string
     */
    protected string $eventType = '';

    /**
     * Class name of the API which will use this config
     * e.g. 'Schoolbox', 'Vcal'
     *
     * Public because of Mapper
     *
     * @var string
     */
    protected string $connectorClass = '';

    /**
     * Corresponds to the luEventType table
     *
     * This has historically been mixed usages when setting up the
     * calendar config object which is serialized into the dbj
     *
     * Upon unpacking its vars into an instance, the instance
     * needs to be particularly forgiving of the datatypes that
     * were once there - it can be either string or int depending
     * on when the calendar config was first manufactured.
     *
     * These should eventually be ints when you rebuild the calendar
     * config schema into a json format, which should make this
     * issue go away
     */
    protected $luEventType = '1';

    /**
     * Whether or not the current user can view these events
     * Setting this to False prevents event accumulation
     *
     * @var bool
     */
    protected bool $hasPermissions = false;


    /**
     * Gets a sensible default label for this connector.
     *
     * @return string
     */
    abstract public function getDefaultLabel(): string;

    /**
     * Render event type lookup selector with the currently chosen
     * value preselected
     *
     * @param SitePage $page
     * @param int $preselected
     */
    protected function renderEventType(SitePage $page, int $preselected): void
    {
        $eventHandler = new Calendar();

        $allLookupEventTypes = $eventHandler->getTypeList();

        if (is_array($allLookupEventTypes) && count($allLookupEventTypes)) {
            foreach ($allLookupEventTypes as $currentLookupEventType) {
                $selected = '';

                if ((int)$currentLookupEventType['eventTypeID'] === $preselected) {
                    $selected = 'selected="selected"';
                }

                $page->setVar([
                    'LUEVENTTYPE_VALUE'    => $currentLookupEventType['eventTypeID'],
                    'LUEVENTTYPE_NAME'     => Scrub::text($currentLookupEventType['eventType']),
                    'LUEVENTTYPE_SELECTED' => $selected,
                ]);

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
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }


    /**
     * Get event type
     *
     * @return string
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }


    /**
     * Set connector class
     *
     * @param string $connectorClass
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function setConnectorClass(string $connectorClass): self
    {
        $this->connectorClass = $connectorClass;
        return $this;
    }


    /**
     * Get connector class
     *
     * @return string
     */
    public function getConnectorClass(): string
    {
        return $this->connectorClass;
    }


    /**
     * Set id
     *
     * @param int $id
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }


    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }


    /**
     * Set luEventType
     *
     * @param string|int $luEventType
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function setLuEventType($luEventType): self
    {
        $this->luEventType = $luEventType;
        return $this;
    }


    /**
     * Get luEventType
     *
     * @return mixed
     */
    public function getLuEventType()
    {
        return $this->luEventType;
    }


    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }


    /**
     * @param  string $label
     *
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * failed Count
     */
    public int $failedCount = 0;
    public function getFailedCount()
    {
        return $this->failedCount;
    }
    public function setFailedCount(int $count)
    {
        $this->failedCount = $count;
    }
}
