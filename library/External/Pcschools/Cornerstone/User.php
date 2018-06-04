<?php
/**
 * External_Pcschools_Cornerstone_User
 *
 * Cornerstone-specific customisations of PC Schools
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


class External_Pcschools_Cornerstone_User extends External_Pcschools_User
{

    /**
     * A special stored proc just for cornerstone
     * Every other PC School instance will break if clients use this
     *
     * @var string
     */
    protected $timetableStoredProc = 'spSelTimeTableSchoolBox';
}