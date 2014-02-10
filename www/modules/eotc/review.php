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
$allStatus = $eventMapper->findAll();

foreach ($allStatus as $currentStatus) {
	if ($currentStatus->count()) {
		foreach ($currentStatus as $currentEvent) {
			new \Alaress_Probe($currentEvent->getMetadata()->getStatus()->getName());
			new \Alaress_Probe($currentEvent->getNote());
		}

		echo '<hr>';
	}
}

exit();