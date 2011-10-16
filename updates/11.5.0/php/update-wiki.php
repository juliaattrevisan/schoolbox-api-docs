<?php
/**
 * Update Wikis
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$report = array();

$wikiHandler = new Wiki();

$allWikis = $wikiHandler->getAll();

if (is_array($allWikis) && count($allWikis)) {
	foreach($allWikis as $currentWiki) {
		$thisWiki = $wikiHandler->getByID($currentWiki['wikiID']);

		/* Get the heading used in the most recent revision
		 * saved for the current wiki
		 */
		$latestRevision = $wikiHandler->getLatestRevision($currentWiki['wikiID']);

		$url = new Url($latestRevision['heading']);

		/* Generate a slug for it and update the wiki
		 */
		$data = array(
			'wikiID' => $currentWiki['wikiID'],
			'slug' => $url->getSlug()
		);

		/* Only update non-deleted items
		 */
		if ($currentWiki['deleted'] == 'false') {
			$report[] = "Updating '{$latestRevision['heading']}' to use slug {$data['slug']}";

			$wikiHandler->db->update('wiki', 'wikiID', $data);
		}
	}

	print_r(implode("\n", $report));
}
else {
	echo "No wiki entries found.";
}

echo "\n\n";
