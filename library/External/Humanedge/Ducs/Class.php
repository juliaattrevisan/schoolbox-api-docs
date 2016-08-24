<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of class connection.
 */
class External_Humanedge_Ducs_Class extends External_Humanedge_Class
{
    /**
     * DUCS class-teacher relations don't require that the timetable entry
     * has a room attached to it, so override base functionality here.
     *
     * @var bool
     */
    protected $requireClassTeachersHaveRooms = false;
}
