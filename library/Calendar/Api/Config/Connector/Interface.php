<?php

/**
 * Calendar_Api_Config_Connector_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
interface Calendar_Api_Config_Connector_Interface
{

    /**
     * Method for uniquely rendering on admin screen
     *
     * $userCampus is an array of campus ids
     * $userGroup is an int
     *
     * @param array $userCampus
     * @param int $userGroup
     * @return mixed
     */
    public function hasPermissions($userCampus, $userGroup);

    /**
     * Render this connector config
     *
     * @param SitePage $page
     * @return mixed
     */
    public function render($page);
}
