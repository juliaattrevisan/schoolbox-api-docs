<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$eventMapper = new \EOTC\EventMapper();
$event = $eventMapper->findById(1);

new \Alaress_Probe($event);

exit();