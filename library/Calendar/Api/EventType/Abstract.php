<?php
use Schoolbox\User;

/**
 * Calendar_Api_EventType_Abstract
 *
 * This is used to remember the calendar_connector id ($connectorId) because each event needs to know from whence it
 * came. This id is used to retrieve a particular event record from whatever source, to display event detail screen in
 * the Mobile version of the calendar (in Desktop, event details are rendered inline)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Api_EventType_Abstract implements Calendar_Api_EventType_Interface
{
    /**
     * The calendar_connector id from which this event was retrieved.
     * This is used to create a functioning event detail screen in Mobile.
     *
     * @var int
     */
    private $connectorId;

    /**
     * An instance of User
     *
     * @var Schoolbox\User
     */
    private $user;


    /**
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /* Getters/Setters
     */

    /**
     * Set calendar_connector id
     *
     * @param int $connectorId
     */
    public function setConnectorId($connectorId)
    {
        $this->connectorId = $connectorId;
    }

    /**
     * Get calendar_connector id
     *
     * @return int
     */
    public function getConnectorId()
    {
        return $this->connectorId;
    }


    /**
     * @return User
     */
    protected function getUser(): User
    {
        return $this->user;
    }


    /**
     * Helper method for loading this event type's calendar connector config.
     *
     * @return Calendar_Api_Config_Record|object
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function loadConnectorConfiguration()
    {
        return Calendar_Api_Config_Mapper::getInstance()->findById(
            $this->getConnectorId()
        );
    }
}
