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

require_once __DIR__ . '/../../../conf/core/require-cli.php';
require_once __DIR__ . '/../abstract/SynchroniseAbstract.php';

$sync = new SynchroniseAbstract('Moriah', isset($options['v']), isset($options['perform-update']));

$sync->setStrategies(array('Staff', 'Parent', 'Student'));

if (isset($options['diagnostic'])) {
    $sync->diagnose();
} else {
    $sync->execute();
}
