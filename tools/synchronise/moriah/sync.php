<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$options = getopt("v", array('perform-update', 'diagnostic'));

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');
require_once(dirname(__FILE__) . '/../abstract/SynchroniseAbstract.php');

$sync = new SynchroniseAbstract('Moriah', isset($options['v']), isset($options['perform-update']));

$sync->setStrategies('Staff', 'Parent', 'Student');

if(isset($options['diagnostic'])) {
    $sync->diagnose();
}
else {
    $sync->execute();
}
