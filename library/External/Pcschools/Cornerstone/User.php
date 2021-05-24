<?php

/**
 * External_Pcschools_Cornerstone_User
 *
 * Cornerstone-specific customisations of PC Schools
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

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
