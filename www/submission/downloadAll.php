<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Application\WebRequest\ShutdownHandler;

SitePage::extendExecutionTime(300);

$page = new SitePage;

if (is_numeric($_GET['id'])) {
    $box_id = $_GET['id'];

    // check that we have read access to the submissions directory
    if (!is_readable(Cfg::get('submission_dir') . '/')) {
        error(9, "the requested submissions could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
    }

    $boxMapper        = new SubmissionBoxMapper();
    $submissionMapper = new SubmissionBox\Submission\SubmissionMapper();

    $submissionBox = $boxMapper->findById($box_id);
    $zipName = Scrub::text($submissionBox->getName());

    $group = new Group();
    if ($group->getPermission($submissionBox->getCategory()->getId()) >= WRITE_ACCESS) {
        /** @var SubmissionBox\Submission\Submission[] $submissionList */
        $submissionList = $submissionMapper->findByColumnList(
            ['box_id' => $box_id, 'draft' => false,],
            ['order' => ['submitted' => 'desc']]
        );
        // Download all files, even for submission boxes which don't support
        // multiple files.
        // (This is because a single submission can contain both an online and
        //  uploaded submission.)
        $zip = new SubmissionZipFolder($zipName, $submissionList);
        ShutdownHandler::instance()->registerFunction([$zip, 'cleanUp'], 10000);
        $zip->sendZip();
    } else {
        error(5);
    }
}
