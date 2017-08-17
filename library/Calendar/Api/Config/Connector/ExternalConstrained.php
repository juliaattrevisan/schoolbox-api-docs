<?php

/**
 * ExternalConstrained
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Calendar_Api_Config_Connector_ExternalConstrained extends Calendar_Api_Config_Connector_External
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected $connectorClass = 'ExternalConstrained';

    /**
     * Maximum number of days to obtain, or something like that?
     *
     * @var int
     */
    protected $duration = 2;

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return 'External Database';
    }

    /**
     * Render configs into admin screen. Currently, the only setting is the
     * event type.
     *
     * @param  SitePage $page
     * @return void
     */
    public function render($page)
    {
        $page->setVar([
            'CONNECTOR_ID' => $this->getId(),
            'CONNECTOR_LABEL' => Scrub::text($this->getLabel()),
            'CONNECTOR_CLASS' => Scrub::text($this->getConnectorClass()),
            'CONNECTOR_DURATION' => $this->getDuration(),
        ]);
        $page->setVar('has_multiple_options_block', '');
        $this->renderEventType($page, $this->getLuEventType());
    }

    /**
     * Currently all users have access to these events.
     *
     * @param $userCampus
     * @param $userGroup
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     * @return $this
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }
}
