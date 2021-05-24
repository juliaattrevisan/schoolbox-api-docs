<?php

/**
 * NonTeaching Period
 *
 * A Lesson instance that does not involve any students learning, or
 * any teachers teaching. This is so that occurrences of Recess,
 * Lunch, after-school duties/curricular activities can be displayed
 * in the timetable
 *
 * Connectors should provide a means of determining this
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Timetable\Period;

class NonTeaching extends Base
{
    /**
     * Determine whether this instance is a Teaching
     * Period (or a Non-Teaching Period)
     *
     * @var bool
     */
    protected $isTeaching = false;
}
