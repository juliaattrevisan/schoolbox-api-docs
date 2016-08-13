<?php
/**
 * Takes etherpad instances from redis (memory) and saves in DB, run periodically
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Etherpad Sync', array('level'=>200) );
$logger = $kernel->logger();



/* Load the pad from our db to get its groupId
 * Error out if you can't find it
 */
$padMapper = new \Etherpad\PadMapper();

$allPads = $padMapper->findAll();

/* Save pad contents to db, just in case
 */
$client = new \EtherpadLite\Client(Cfg::get('etherpad_api_token'));
$logger->info(sprintf("Commencing etherpad sync, processing %d pads", count($allPads)));

//var_dump($allPads);

foreach($allPads as $currentPad) {
	$getTextResult = $client->getHTML($currentPad->getPadId());

	if ($getTextResult->getMessage() == 'ok') {
		$data = $getTextResult->getData();
		$currentPad->setBody($data['html']);
        $padMapper->save($currentPad);
        $logger->info(sprintf("Successfully saved pad %d: %s", $currentPad->getId(), $getTextResult->getMessage()));


    } else {
	    $logger->warning(sprintf("Failed to get pad %d with key %s: %s", $currentPad->getId(), $currentPad->getPadId(), $getTextResult->getMessage()));
    }

	/* Save it!
	 */
}
$logger->info("Etherpad sync complete");

