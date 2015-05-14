<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new SitePage();

$action = $_POST['action'];
$ownerId = $_POST['userId'];
$pageId = 'showcase';

$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $ownerId, $pageId);

if ($authenticator->canModify()) {
	if ($action == 'unpublish') {
		$showcaseItemId = $_POST['showcaseItemId'];

		$showcasedItem = \Eportfolio\ShowcaseMapper::getInstance()->findById($showcaseItemId);

		\Eportfolio\ShowcaseMapper::getInstance()->delete($showcasedItem);
	}
	else if ($action == 'publish') {
		$boxId = $_POST['boxId'];
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

        // Find the submission box
        try {
            $box = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findById($boxId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // "submission box does not exist"
            error(17);
            exit;
        }

		/* Check if the submission box is already being showcased; if so, ignore request
		 */
		$existing = \Eportfolio\ShowcaseMapper::getInstance()->findByColumnList(
			array(
				'uid'               => $ownerId,
				'submission_box_id' => $boxId,
			)
		);

		if (!$existing->count()) {
			$showcasedItem = new \Eportfolio\Showcase();
			$showcasedItem->setSubmissionBox($box);
			$showcasedItem->setUid($ownerId);
			$showcasedItem->setCreatedAt($now);
			$showcasedItem->setUpdatedAt($now);

			\Eportfolio\ShowcaseMapper::getInstance()->create($showcasedItem);
		}
	}

	$page->backToTheFuture(1);
}
else {
    error(19);
}