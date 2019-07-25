#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Takes etherpad instances from redis (memory) and saves in DB, run periodically
 */
$app = new class extends CliTool\App
{
    /**
     * Hands off execution over to the App
     */
    public function run(): void
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var Etherpad\PadMapper $padMapper */
        $padMapper = $mapperFactory->get(Etherpad\PadMapper::class);
        $logger    = $this->getLogger();

        // Load the pad from our db to get its groupId. Error out if you can't find it
        $allPads = $padMapper->findAll();

        // Save pad contents to db, just in case
        $client = new EtherpadLite\Client($this->getConfig()->get('etherpad_api_token'));
        $logger->info(sprintf("Commencing etherpad sync, processing %d pads", count($allPads)));

        $db        = $this->getDatabaseInternal();
        $maxPacket = $db->executeQuery("SHOW VARIABLES WHERE Variable_name = 'max_allowed_packet'")->fetchAll()[0]['Value'];


        foreach ($allPads as $currentPad) {
            $getTextResult = $client->getHTML($currentPad->getPadId());

            if ($getTextResult->getMessage() === 'ok') {
                $data = $getTextResult->getData();
                $currentPad->setBody($data['html']);
                $bodyHtmlLength = strlen($data['html']);

                if ($bodyHtmlLength > $maxPacket) {
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
        }
        $logger->info("Etherpad sync complete");
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();

