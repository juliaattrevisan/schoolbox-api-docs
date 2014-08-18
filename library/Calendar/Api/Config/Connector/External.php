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
     * Render configs into admin screen. Currently, the only setting is the
     * event type.
     *
     * @param $page
     * @return mixed|void
     */
    public function render($page)
    {
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
}
