<?php
/**
 * Teaching Period
 *
 * A Teaching class. That is, a Lesson instance that actually involves
 * students learning something/teachers teaching something
 *
 * Connectors should provide a means of determining this
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Timetable\Period;

class Teaching extends Base
{
    /**
     * Determine whether this instance is a Teaching
     * Period (or a Non-Teaching Period)
     *
     * @var bool
     */
    protected $isTeaching = true;
}