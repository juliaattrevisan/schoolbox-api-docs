<?php
/**
 * Get a json-encoded list of textboxes in a given category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../../../include/Bootstrap.php');

/* Start a session
 */
$page = new SitePage();
$groupHandler = new Group();
$textboxHandler = new TextBox();
$allElements = array();

/* Check permissions
 */
if (is_numeric($_GET['categoryID'])) {
	if ($groupHandler->getPermission($_GET['categoryID']) >= WRITE_ACCESS) {
		$allTextboxes = $textboxHandler->getAllByCategory($_GET['categoryID']);

		if (is_array($allTextboxes) && count($allTextboxes)) {
			foreach($allTextboxes as $currentTextbox) {
				$element = array(
					'name' => Scrub::text($currentTextbox['heading']),
					'value' => $currentTextbox['textBoxID'],
				);

				$allElements[] = $element;
			}
		}

		echo json_encode($allElements);
	}
}


