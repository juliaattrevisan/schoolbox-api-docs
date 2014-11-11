<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$connector = new \EOTC\EventConnector();

$allEvents = $connector->getEvent(1);

foreach ($allEvents as $currentEvent) {
    if ($currentEvent->getPublishDate()->getUnixTimestamp() > $_SERVER['REQUEST_TIME']) {
        $cssClass = 'translucent';
    }
    else {
        $cssClass = '';
    }
}

echo $cssClass;

die();
