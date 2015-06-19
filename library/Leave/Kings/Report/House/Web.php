<?php
/**
 * Leave_Kings_Report_House_Web
 *
 * Online house report as found in the Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Leave_Kings_Report_House_Web extends Leave_Core_Report_House_Web
{
    /**
     * Offsets from the start time of a day as shown on the report.
     *
     * @var array|int[]
     */
    protected $offsets = [
        'morningStart' => 7,
        'dayStart'     => 11,
        'nightStart'   => 21,
        'nightEnd'     => 31,
    ];
}
