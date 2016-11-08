<?php

/**
 * Calendar_Api_Config_Connector_Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Connector_EOTC extends Calendar_Api_Config_Connector_Abstract
{

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return 'EOTC';
    }

    /**
     * Checks if EOTC module is enabled. If not, throw Exception.
     * @throws DomainException
     * @return true If no exception thrown
     */
    protected function checkModuleEnabled()
    {
        //Ensure EOTC module is enabled
        $configMapper = \Schoolbox\Config\ConfigMapper::getInstance();
        if(0 == $configMapper->get('eotc_enabled')){
            throw new DomainException('EOTC Module is disabled');
        }
        return true;
    }

    /**
     * Render configs into admin screen
     *
     * @param $page
     *
     * @return mixed|void
     */
    public function render($page)
    {
        $page->setVar(
            array(
                'CONNECTOR_ID'    => $this->getId(),
                'CONNECTOR_LABEL' => $this->getLabel(),
                'CONNECTOR_CLASS' => $this->getConnectorClass(),
            )
        );
        $page->setVar('has_multiple_options_block', '');
    }

    /**
     * Check if current user has permissions to see this event
     *
     * @param $userCampus
     * @param $userGroup
     *
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
