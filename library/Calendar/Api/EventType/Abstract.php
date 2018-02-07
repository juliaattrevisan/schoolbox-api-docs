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
     * The calendar_connector.id from which this event was retrieved.
     * This is used to create a functioning event detail screen in Mobile.
     *
     * @var int
     */
    private $connectorId;

    /**
     * The user whose calendar we are viewing (not the current session user)
     *
     * @var Schoolbox\User
     */
    private $user;


    /**
     * @param int  $connectorId
     * @param User $user
     */
    public function __construct(int $connectorId, User $user)
    {
        $this->connectorId = $connectorId;
        $this->user        = $user;
    }

    /* Getters/Setters
     */


    /**
     * Get calendar_connector id
     *
     * @return int
     */
    protected function getConnectorId(): int
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
    protected function loadConnectorConfiguration()
    {
        return Calendar_Api_Config_Mapper::getInstance()->findById(
            $this->connectorId
        );
    }
}
