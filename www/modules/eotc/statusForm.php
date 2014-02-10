<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../../include/Bootstrap.php');

$page = new SitePage();

$eventMapper = new \EOTC\EventMapper();

/* Spawn an existing event if id provided
 * Otherwise spawn a new one
 */
try {
	$event = $eventMapper->findById($_POST['id']);
}
catch (\Exception $e) {
	error(160);
}

/* Update values
 */
$event->getMetadata()->setStatus($_POST['status']);
$event->getMetadata()->setComment($_POST['comment']);

/* Notifications
 */

/* Save it
 */
$metadataMapper = new \EOTC\EventMetadataMapper();
$metadataMapper->save($event->getMetadata());

new \Alaress_Probe($_POST);
die();