<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of user retrieve methods.
 */
class External_Humanedge_Ducs_User extends External_Humanedge_User
{
    /**
     * DUCS teacher-subject relations don't require that the timetable entry
     * has a room attached to it, so override base functionality here.
     *
     * @var bool
     */
    protected $requireTeacherSubjectsHaveRooms = false;
}
