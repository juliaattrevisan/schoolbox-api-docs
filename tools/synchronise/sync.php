<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$options = getopt("v", array('perform-update', 'diagnostic', 'site-location:'));

echo "[".date('Y-m-d H:i:s')."] Running sync script\n";

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

if (Cfg::get('external_sync_data') == 1) {

    if (isset($options['site-location'])) {
        $siteLocation = $options['site-location'];
    } else {
        $siteLocation = Cfg::get('site_location');
    }

    // check for Saint Something as best we can in a simple fashion
    if(preg_match('/^st([^aeiouyr])/', $siteLocation, $matches) > 0) {
        // uppercase the 3rd character (eg stmichaels to stMichaels)
        $siteLocation[2] = strtoupper($siteLocation[2]);
    }

    $sync = new Synchronise_Facade(ucfirst($siteLocation), isset($options['v']), isset($options['perform-update']));

    $strategies = explode(' ', Cfg::get('sync_patterns'));

    $sync->setStrategies($strategies);

    if (isset($options['diagnostic'])) {
        $sync->diagnose();
    } else {
        $sync->execute();

        // advise Rerouter that all bindings need to be rerun
        $cmdr = new \Schoolbox\Realtime\Rerouter\RerouterCommander(\Schoolbox\UserMapper::getInstance());
        $cmdr->adviseBindAll();
    }

}
else {
    die("Data synchronisation is not enabled.\n");
}
