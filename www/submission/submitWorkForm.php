<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Video\QueueMapper;

$page = new SitePage;

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

// attachment submission
if (!is_numeric($_POST['box_id'])) {
    error(2);
}

if (!strlen(trim($_POST['body'])) && !count($_POST['attachment'])) {
    // no body && no files == no dice
    error(2);
}

$subMap = Submission\SubmissionMapper::getInstance();
$boxMap = SubmissionBoxMapper::getInstance();

/** @var Collection|Submission\File[] $submissions */
$submissions = new Collection();

// Allow teachers to impersonate students
/** @var Schoolbox\User $user */
if (isset($_POST['user_id']) && $_POST['user_id'] != $sessionUser->getId()) {
    $user = \Schoolbox\UserMapper::getInstance()->findById($_POST['user_id']);
    $returnHandler = new SubmissionReturn();

    try {
        $box = $boxMap->findById($_POST['box_id']);
    }
    catch (\Schoolbox\Mapper\Exception $e) {
        error(17);
        exit();
    }

    if (!$box->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
        error(25);
        exit();
    }
} else {
    $user = $_SESSION['user'];
}

try {

    $box = $boxMap->findById($_POST['box_id']);

    // check uploading to this box is permitted
    $boxMap->checkUploadability($box, $user);

    $now = Alaress_Date_Format::now();

    // If there's a post body, create a new non-draft out of it, and then a submission
    if (trim($_POST['body'])) {

        $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->acceptStream('online-submission.html', $_POST['body'], false);

        $submission = $subMap->createModel()
            ->setCreatedAt($now)
            ->setStorage($storage)
            ->setFilename('Online Submission')
            ->setSubmissionBox($box)
            ->setOwner($user)
            ->setLate($box->getDueDate()->lt($now))
        ;

        $submissions->add($submission);
    }

    // If there's attachments, create a submission for each one
    if (is_array($_POST['attachment'])) {

        $factory = new CollectorFactory();

        /** @var IStorage[] $subs */
        $attachments = $factory->build($_POST['attachment'])->getAll();

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
