<?php
/**
 * Event source configuration for the external database events.
 *
 * Currently, the timetable is the only event type that matches this.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_Config_Connector_External extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected $connectorClass = 'External';

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
    private function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     * @return $this
     */
    private function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }
}
