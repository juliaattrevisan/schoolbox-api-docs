<?php
/**
 * Plagscan scanner
 *
 * Proactively queries Plagscan (instead of relying on its callback) for scans
 * on documents passed to it.
 *
 * @author           Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright        Copyright 2013, Alaress Pty Ltd
 * @link             http://www.alaress.com.au/
 * @idea_stolen_from Matt Day <matt.day@alaress.com.au>
 */
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Gateway as PlagscanGateway;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Plagscan Scanner', array('level'=>200));
$log = $kernel->logger();

$mapper = PlagscanMapper::getInstance();

if (!$mapper->getGateway()) {

    /* Plagscan not configured - nothing to do
     */
    $log->warning("Plagscan scan did not run: username/api key is not configured correctly");
    exit();
}

$log->info("Plagscan is now choosing modes and options");

$opts = getopt('', ['re-retrieve']);
$reRetrieveMode = isset($opts['re-retrieve']);

/* Re-scan plagscan records
 */
if ($reRetrieveMode) {
    $log->info("Scanner running in re-retrieve mode.");
    $allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_COMPLETE);
}
/* Scan currently-pending plagscan records
 */
else {
    $log->info("Scanner running in scan mode.");
    $allDocuments = $mapper->findByColumn('status', Plagscan::STATUS_QUEUED);
}

$log->info("Plagscan now processing {$allDocuments->count()} documents");

$numUpdated = 0;

/** @var Plagscan $currentDocument */
foreach ($allDocuments as $currentDocument) {

    /* Sometimes plagscan gives you back -1 as the plagscan_id
     * In this case, just skip it, so the daemon doesn't get
     * stuck trying to process the unprocessable
     *
     * I am also going to assume that $currentDocument->getPlagscanId()
     *  has to be greater than zero. I can't think of why zero would be valid.
     */
    if ($currentDocument->getPlagscanId() > 0) {
        $stats = $mapper->getGateway()->retrieve($currentDocument->getPlagscanId(), PlagscanGateway::MODE_RETRIEVE_STATISTICS_ONLY);

        if (!$stats) {
            $log->error("Plagscan failed at Gateway retrieval. Check API key and username?");
            exit(2);
        }

        // Is this document scannable? If not, mark as such and don't scan again.
        if ((string)$stats->MSG === 'ERR') {
            if (!$reRetrieveMode) {
                $currentDocument->setStatus(Plagscan::STATUS_UNSCANNABLE);
                $mapper->save($currentDocument);
            }

            $log->error("Document {$currentDocument->getId()} unscannable: ". print_r($stats, true));

            continue;
        }

        // Has a level been retrieved yet? How can I tell?
        $level = (string)$stats->LEVEL;

        if (strlen($level) > 0) {
            if (!$reRetrieveMode) {
                $currentDocument->setScore($level / 10)->setStatus(Plagscan::STATUS_COMPLETE);
            }

            $currentDocument->setHtml($mapper->getGateway()->retrieve(
                $currentDocument->getPlagscanId(),
                PlagscanGateway::MODE_RETRIEVE_HTML_INTERACTIVE
            ));

            $mapper->save($currentDocument);
            $numUpdated++;

            $log->info("Retrieved scan status for {$currentDocument->getPlagscanId()}");
        }
        else {
            $response = $mapper->getGateway()->check($currentDocument->getPlagscanId());

            $log->warning("Bad level for document, still pending result: ".print_r($stats,true).print_r($response,true));
        }
    }
}

$log->info("Plagscan scan completed. $numUpdated documents were scanned");

/* Also tell me how many died
 */
$numFailed = $allDocuments->count() - $numUpdated;

if ($numFailed) {
    $log->info("$numFailed documents failed scanning");
}
