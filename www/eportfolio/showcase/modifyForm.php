<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$action  = $_POST['action'];
$ownerId = $_POST['userId'];

try {
    $owner = \Schoolbox\UserMapper::getInstance()->findById($ownerId);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
    exit;
}

$authenticator = new Eportfolio_Authenticator($_SESSION['user'], $owner);

if ($authenticator->canModify()) {
    if ($action == 'unpublish') {
        \Eportfolio\ShowcaseMapper::getInstance()->unpublish((int)$_POST['assessmentId'], (int)$ownerId);
    } elseif ($action == 'publish') {
        if (isset($_POST['boxId'])) {
            $boxId = $_POST['boxId'];

            // Find the submission box
            try {
                $box = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findById($boxId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // "submission box does not exist"
                error(17);
                exit;
            }
        } elseif (isset($_POST['assessmentId'])) {
            try {
                $box = Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $_POST['assessmentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // "submission box does not exist"
                error(17);
                exit;
            }
        } else {
            error(2);
            exit;
        }

        $now = \Alaress_Date_Format::now();


        /* Check if the submission box is already being showcased; if so, ignore request
         */
        $existing = \Eportfolio\ShowcaseMapper::getInstance()->findByColumnList(
            [
                'uid'               => $ownerId,
                'submission_box_id' => $box->getId(),
            ]
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
} else {
    error(19);
}
