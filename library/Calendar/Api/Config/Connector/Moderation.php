<?php

/**
 * Config connector for resource booking.
 *
 * This connector is for bookings that a particular user can moderate.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_Config_Connector_Moderation extends Calendar_Api_Config_Connector_Abstract
{

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return 'Resource Moderation';
    }

    /**
     * Checks if Resource Booking module is enabled. If not, throw Exception.
     * @throws DomainException
     * @return true If no exception thrown
     */
    protected function checkModuleEnabled()
    {
        //Ensure ResourceBooking module is enabled
        $configMapper = \Schoolbox\Config\ConfigMapper::getInstance();
        if(0 == $configMapper->get('resource_booking_enabled')){
            throw new DomainException('Resource Booking Module is disabled');
        }
        return true;
    }

    /**
     * Render configs into admin screen
     *
     * @param  SitePage $page
     *
     * @return void
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
     * @param  $userCampus
     * @param  $userGroup
     *
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
