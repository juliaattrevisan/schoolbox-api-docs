<?php
/**
 * Takes etherpad instances from redis (memory) and saves in DB, run periodically
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Alaress\Database\Connector\IConnector;


require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel('Etherpad Sync', array('level' => 200));
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

/** @var IConnector $db */
$db = $kernel->get('databaseInternal');
$maxPacket = $db->executeQuery("SHOW VARIABLES WHERE Variable_name = 'max_allowed_packet'")->fetchAll()[0]['Value'];


foreach ($allPads as $currentPad) {
    $getTextResult = $client->getHTML($currentPad->getPadId());

    if ($getTextResult->getMessage() == 'ok') {
        $data = $getTextResult->getData();
        $currentPad->setBody($data['html']);
        $bodyHtmlLength = strlen($data['html']);

        if($bodyHtmlLength > $maxPacket){
            $logger->error(sprintf('Pad (%d) body HTML size (%d) exceeds max packet (%d), skipping...', $currentPad->getId(), $bodyHtmlLength, $maxPacket));
            continue;
        }

        try {
            $padMapper->save($currentPad);
            $logger->info(sprintf("Successfully saved pad %d: %s", $currentPad->getId(), $getTextResult->getMessage()));
        } catch (Exception $e) {
            $logger->error(sprintf("Error while saving pad %d: %s", $currentPad->getId(), $e->getMessage()));
        }

    } else {
        $logger->warning(sprintf("Failed to get pad %d with key %s: %s", $currentPad->getId(), $currentPad->getPadId(), $getTextResult->getMessage()));
    }

    /* Save it!
     */
}
$logger->info("Etherpad sync complete");

