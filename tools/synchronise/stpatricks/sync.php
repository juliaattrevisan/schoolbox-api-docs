<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$options = getopt("v", array('perform-update', 'diagnostic'));
require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
$sync = new Synchronise_Facade('Stpatricks', isset($options['v']), isset($options['perform-update']));

// these should be in order of dependencies (and vertically formatted for easier visual scanning)
$sync->setStrategies(array( // DEPENDENCIES
    'Staff',                // -
    'Student',              // -
    'Parent',               // -
    'ParentRelation',       // student, parent
    'Class',                // staff
    'Enrolment',            // student, class
    'Timetable',            // student, staff
));

if (isset($options['diagnostic'])) {
    $sync->diagnose();
} else {
    $sync->execute();
}
