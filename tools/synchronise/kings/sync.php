<?php
/**
 * Perform Schoolbox User/Denbigh synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../library/Bootstrap.php');

// manually define list of strategies to implement
$strategyList = array(
    'Synchronise_Denbigh_Kings_Student',
    //'Synchronise_Denbigh_Kings_Staff',
    'Synchronise_Denbigh_Kings_Parent'
);

foreach($strategyList as $currentStrategy) {
    $sync = new Synchronise_Denbigh_Main($currentStrategy);

    // set params
    $sync->setPerformUpdate(true); // false - don't actually *touch* anything... (default)
    $sync->setVerbose(true);       // true  - show me what you're doing! (default)
    $sync->generateTitle();

    // GO!
    $sync->synchronise();

    echo "\n\n";
}
