<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

/* Load the pad from our db to get its groupId
 * Error out if you can't find it
 */
$padMapper = new \Etherpad\PadMapper();

$allPads = $padMapper->findAll();

/* Save pad contents to db, just in case
 */
$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));

foreach($allPads as $currentPad) {
	$getTextResult = $client->getHTML($currentPad->getPadId());

	if ($getTextResult->getMessage() == 'ok') {
		$data = $getTextResult->getData();
		$currentPad->setBody($data['html']);
	}

	/* Save it!
	 */
	$padMapper->save($currentPad);
}
