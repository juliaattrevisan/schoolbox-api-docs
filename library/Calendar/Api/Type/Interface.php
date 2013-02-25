<?php
/**
 * Calendar_Api_Type_Interface
 *
 * Each method must return an instance of Calendar_Event_Collection
 * containing Calender_Event_Type subclasses in their stacks.
 *
 * When adding a new type of event collection e.g. getDayEvents(),
 * add it here first.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Calendar_Api_Type_Interface {
    public function getEvents($currentDay);
}
