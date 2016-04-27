<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Video\QueueMapper;

$page = new SitePage;

// attachment submission
if (!is_numeric($_POST['box_id'])) {
    error(2);
}

if (!strlen(trim($_POST['body'])) && !count($_POST['attachment'])) {
    // no body && no files == no dice
    error(2);
}

$subMap = Submission\FileMapper::getInstance();
$boxMap = SubmissionBoxMapper::getInstance();

/** @var Collection|Submission\File[] $submissions */
$submissions = new Collection();

// Allow teachers to impersonate students
/** @var Schoolbox\User $user */
if (isset($_POST['user_id']) && $_POST['user_id'] != $_SESSION['user']->getId()) {
    $user = \Schoolbox\UserMapper::getInstance()->findById($_POST['user_id']);
    $returnHandler = new SubmissionReturn();
    $returnHandler->securityCheck($_POST['box_id']);
} else {
    $user = $_SESSION['user'];
}

// if there's a post body, we need to undraft the draftl; otherwise we ignore that
if (trim($_POST['body'])) {
    $drafts = $subMap->getDrafts($user->getId(), $_POST['box_id'], 1);
    if ($drafts->count()) {
        // Create a new item that isn't a draft, instead of resaving the old one.
        $drafts->first()->setId(null)->setDraft(false);
        $submissions->add($drafts->first());
    }
}

if (is_array($_POST['attachment'])) {
    try {

        $box = $boxMap->findById($_POST['box_id']);

        // check uploading to this box is permitted
        $boxMap->checkUploadability($box, $user);

        $factory = new CollectorFactory();

        /** @var IStorage[] $subs */
        $attachments = $factory->build($_POST['attachment'])->getAll();

        $now = Alaress_Date_Format::now();

        foreach ($attachments as $storage) {
            $submission = $subMap->createModel()
                ->setCreatedAt($now)
                ->setStorage($storage)
                ->setFilename($storage->getFilename())
                ->setSubmissionBox($box)
                ->setOwner($user)
                ->setLate($box->getDueDate()->lt($now))
            ;

            $submissions->add($submission);
        }

    } catch (Schoolbox\Mapper\Exception $e) {
        // something didn't exist

        // make sure we save whatever we got so far
        $subMap->save($submissions);
        error(9, $e->getMessage());

    } catch (Schoolbox\Resource\Exception $e) {
        // something wasn't allowed to exist

        // make sure we save whatever we got so far
        $subMap->save($submissions);
        error(9, $e->getMessage());

    } catch (\InvalidArgumentException $e) {
        error(9, $e->getMessage());
        exit;
    }

}

$subMap->save($submissions);

// submissions have been saved: the user can return now, and we'll keep doing some housekeeping back here
// argument[1] ('true') here sends the redirect header and closes the connection, but allows us to continue processing
$page->localRedir('/submission/submissionHistory/' . $_POST['box_id'] . '/' . $user->getId(), true);


/* POST-CLIENT PROCESSING */


$plagscanApiKey   = Config::get('plagscan_api_key');
$plagscanUsername = Config::get('plagscan_username');

$vqMapper = QueueMapper::getInstance();

foreach ($submissions as $submission) {
    $submissionId = $submission->getId();

    /* Submit to PlagScan for plagiarism check */
    if ($plagscanApiKey && $plagscanUsername && is_numeric($submissionId)) {
        $plagMap = Plagscan_DocumentMapper::getInstance();
        $plagMap->save($plagMap->createModel()->setFile($submission));
    }
}
