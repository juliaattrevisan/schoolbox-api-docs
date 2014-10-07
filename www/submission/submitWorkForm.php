<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\UserMapper;
use Schoolbox\Video\QueueMapper;

$page = new SitePage;

// attachment submission
if (is_numeric($_POST['box_id'])) {

    $subMap = SubmissionMapper::getInstance();
    $boxMap = SubmissionBoxMapper::getInstance();

    /** @var Collection|Submission[] $submissions */
    $submissions = new Collection();

    if (is_array($_POST['attachment'])) {
        try {

            $box = $boxMap->findById($_POST['box_id']);

            // check uploading to this box is permitted
            $boxMap->checkUploadability($box, $_SESSION['user']);

            $factory = new CollectorFactory($subMap);

            /** @var Submission[] $subs */
            $subs = $factory->build($_POST['attachment'])->getAll();

            foreach ($subs as $submission) {
                // all other data have been set for this object
                $submission
                    ->setSubmissionBox($box)
                    ->setOwner($_SESSION['user'])
                    ->loadIsLate();

                $submissions->add($submission);
            }

        } catch (Schoolbox\Mapper\Exception $e) {
            // something didn't exist
            error(9, $e->getMessage());
        } catch (Schoolbox\Resource\Exception $e) {
            // something wasn't allowed to exist
            error(9, $e->getMessage());
        }
    } // legacy fallback file submit
    elseif (isset($_FILES['file']['name']) && $_FILES['file']['name']) {
        $allowedFileHandler = new AllowedFile;
        $allowedFileHandler->handleError($allowedFileHandler->fileSecurityCheck($_FILES['file']));

        $submitDir = Cfg::get('submission_dir') . "/";
        if (!is_writable($submitDir)) {
            error(9, "unable to upload file to submission box.<br />Please try again, and if the problem persists please contact your teacher or system administrator.");
        }

        $user           = $_SESSION['user'];
        $submission_box = $boxMap->findById($_POST['box_id']);

        $submission = new Submission();
        $submission
            ->setSubmissionBox($submission_box)
            ->setOwner($user)
            ->setSubmitted(new Alaress_Date_Format(new DateTime('now'), 'U'))
            ->setFilename($_FILES['file']['name'])
            ->setTempLocation($_FILES['file']['tmp_name'])
            ->setFilesize($_FILES['file']['size'])
            ->loadMimetypeFromFilename()
            ->loadIsLate();

        // Check uploadability before saving
        try {
            $subMap->checkUploadability($submission);
        } catch (Schoolbox\Resource\Exception $e) {
            error(17);
        }

        $submissions->add($submission);

    } /* Exceeded PHP max file size */
    elseif (upload_failed()) {
        error(10);
    }
    else {
        error(2);
    }

    $subMap->save($submissions);

    // submissions have been saved: the user can return now, and we'll keep doing some housekeeping back here
    // argument[1] ('true') here sends the redirect header and closes the connection, but allows us to continue processing
    $page->localRedir('/submission/submissionHistory/' . $_POST['box_id'] . '/' . $_SESSION['id'], true);


    /* POST-CLIENT PROCESSING */


    $plagscanApiKey   = Config::get('plagscan_api_key');
    $plagscanUsername = Config::get('plagscan_username');

    $vqMapper = QueueMapper::getInstance();

    foreach ($submissions as $submission) {
        $submissionId = $submission->getId();

        /* If video add to video processing queue */
        if ($submission->getMimetype()->isVideo()) {
            $videoQ = new Schoolbox\Video\Queue();
            $videoQ->setFileID($submissionId);
            $videoQ->setStatus(QueueMapper::STATUS_PENDING);
            $videoQ->setType(QueueMapper::FILE_TYPE_SUBMISSION);

            $vqMapper->save($videoQ);
        }

        /* Submit to PlagScan for plagiarism check */
        if ($plagscanApiKey && $plagscanUsername && is_numeric($submissionId)) {
            $document = new Plagscan_Document();

            $document->setFileId($submissionId);

            $document->setFile(array(
                'id'   => $submissionId,
                'name' => $submission->getFilename(),
                'file' => $submission->getFileLocation()
            ));
            Plagscan_DocumentMapper::getInstance()->save($document);
        }
    }
} else {
    error(2);
}
