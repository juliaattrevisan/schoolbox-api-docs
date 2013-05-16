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


$mapper = Plagscan_DocumentMapper::getInstance();
$docs   = $mapper->findByColumn('status', Plagscan_Document::STATUS_QUEUED);

//
// Hit the Plagscan API for each document.
$key      = Config::get('plagscan_api_key');
$username = Config::get('plagscan_username');
if (empty($username) || empty($key)) {
	echo strftime("[%F %T] Plagscan scan not run, as Plagscan is not configured\n");
	exit;
}


echo strftime("[%F %T] Plagscan scan started\n");
$gateway      = new Plagscan_Gateway($username, $key);
$updated_docs = 0;
foreach ($docs as $doc) {
	$doc_id = $doc->plagscanId;
	$stats  = $gateway->retrieve($doc_id, Plagscan_Gateway::MODE_RETRIEVE_STATISTICS_ONLY);
	if (!$stats) {
		echo strftime("[%F %T] Plagscan scan failed - check API key and username?\n");
		exit(2);
	}

	//
	// Has a level been retrieved yet? How can I tell?
	$level = (string)$stats->LEVEL;
	if (strlen($level) > 0) {
		$doc->score  = $level / 10;
		$doc->status = Plagscan_Document::STATUS_COMPLETE;
		$doc->html =  $gateway->retrieve($doc_id, Plagscan_Gateway::MODE_RETRIEVE_HTML_INTERACTIVE);
		Plagscan_DocumentMapper::getInstance()->save($doc);
		$updated_docs++;
	}
}

echo strftime("[%F %T] Plagscan scan completed: ");
printf("%d documents marked as completed.\n", $updated_docs);
