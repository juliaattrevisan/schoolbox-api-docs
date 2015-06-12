<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

SitePage::extendExecutionTime(300);

$page = new SitePage;

if (is_numeric($_GET['id'])) {
    $box_id = $_GET['id'];

    // check that we have read access to the submissions directory
    if (!is_readable(Cfg::get('submission_dir') . '/')) {
        error(9, "the requested submissions could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
    }

    $boxMapper        = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
    $submissionMapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper();

    $submissionBox = $boxMapper->findById($box_id);
    $zipName = Scrub::text($submissionBox->getName());

    $group = new Group();
    if ($group->getPermission($submissionBox->getCategory()->getId()) >= WRITE_ACCESS) {
        /** @var \Schoolbox\Assessment\Component\SubmissionBox\Submission\File[] $submissionList */
        $submissionList = \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper::getInstance()
            ->findByColumn('box_id', $box_id, ['order' => ['submitted' => 'desc']]);
        // Does this box support multiple files?
        // If not, only allow most recent submission for each student.
        if (!$submissionBox->getAcceptMultiple()) {
            $newList = [];
            foreach ($submissionList as $submission) {
                if (!isset($newList[$submission->getOwner()->getId()])) {
                    $newList[$submission->getOwner()->getId()] = $submission;
                }
            }
            $submissionList = new \Schoolbox\Collection(array_values($newList));
        }

        $zip = new SubmissionZipFolder($zipName, $submissionList);
        $zip->sendZip();
    } else {
        error(5);
    }
}
