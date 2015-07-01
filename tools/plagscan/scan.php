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

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Alaress\Lumberjack\Logger;

$opts = getopt('', ['re-retrieve']);
$reRetrieveMode = isset($opts['re-retrieve']);

$log = new Logger;

//
// Hit the Plagscan API for each document.
$key      = Config::get('plagscan_api_key');
$username = Config::get('plagscan_username');
if (empty($username) || empty($key)) {
	$log->warning("Plagscan scan not run, as Plagscan is not configured");
	exit;
}

$log->info("Plagscan scan started");

// Get documents
$mapper = Plagscan_DocumentMapper::getInstance();
if ($reRetrieveMode) {
    $log->info("Scanner running in re-retrieve mode");
    $docs = $mapper->findByColumn('status', Plagscan_Document::STATUS_COMPLETE);
} else {
    $log->info("Scanner running in scan mode");
    $docs = $mapper->findByColumn('status', Plagscan_Document::STATUS_QUEUED);
}

$gateway      = new Plagscan_Gateway($username, $key);
$updated_docs = 0;
foreach ($docs as $doc) {

	/** @var Plagscan_Document $doc */
	$doc_id = $doc->getPlagscanId();

    /* Sometimes plagscan gives you back -1 as the plagscan_id
     * In this case, just skip it, so the daemon doesn't get
     * stuck trying to process the unprocessable
     *
     * I am also going to assume that $doc_id has to be greater
     * than zero. I can't think of why zero would be valid.
     */
    if ($doc_id > 0) {
        $stats  = $gateway->retrieve($doc_id, Plagscan_Gateway::MODE_RETRIEVE_STATISTICS_ONLY);
        if (!$stats) {
            $log->error("Plagscan scan failed - check API key and username?");
            exit(2);
        }

        //
        // Is this document scannable? If not, mark as such and don't scan again.
        if ((string)$stats->MSG === 'ERR') {
            if (!$reRetrieveMode) {
                $doc->setStatus(Plagscan_Document::STATUS_UNSCANNABLE);
                Plagscan_DocumentMapper::getInstance()->save($doc);
            }
            $log->error("Document unscannable: ". print_r( $stats, true ));
            continue;
        }

        //
        // Has a level been retrieved yet? How can I tell?
        $level = (string)$stats->LEVEL;
        if (strlen($level) > 0) {
            if (!$reRetrieveMode) {
                $doc->setScore($level / 10)
                    ->setStatus(Plagscan_Document::STATUS_COMPLETE);
            }
            $doc->setHtml(
                $gateway->retrieve(
                    $doc_id,
                    Plagscan_Gateway::MODE_RETRIEVE_HTML_INTERACTIVE
                )
            );
            Plagscan_DocumentMapper::getInstance()->save($doc);
            $updated_docs++;
            $log->info("Retrieved scan status for $doc_id");
        } else {
            $response = $gateway->check($doc_id);
            $log->warning("Bad level for document, still pending result: ".print_r($stats,true).print_r($response,true));
        }
    }
}

$log->info("Plagscan scan completed - $updated_docs documents scanned");
