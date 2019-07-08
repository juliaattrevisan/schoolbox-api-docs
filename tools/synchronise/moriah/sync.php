#!/usr/bin/php
<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

$options = getopt("v", array('perform-update', 'diagnostic'));

require_once __DIR__ . '/../../../conf/core/require.php';
require_once __DIR__ . '/../abstract/SynchroniseAbstract.php';

$kernel = new \Schoolbox\Application\Daemon\Kernel('Moriah Sync');
$sync = new SynchroniseAbstract('Moriah', isset($options['v']), isset($options['perform-update']));

$sync->setStrategies(array('Staff', 'Parent', 'Student'));

if (isset($options['diagnostic'])) {
    $sync->diagnose();
} else {
    $sync->execute();
}
