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

try {
	$event = $eventMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(160);
}

