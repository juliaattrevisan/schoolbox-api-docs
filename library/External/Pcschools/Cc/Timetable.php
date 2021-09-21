<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Cornerstone-specific customisations of PC Schools
 */
class External_Pcschools_Cc_Timetable extends External_Pcschools_Timetable
{

    /**
     * A special stored proc just for cornerstone
     * Every other PC School instance will break if clients use this
     *
     * @var string
     */
    protected $timetableStoredProc = 'spSelTimeTableSchoolBox';
}
