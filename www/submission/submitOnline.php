<?php

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Breadcrumbs;
use Schoolbox\Config\ConfigMapper;

// not in sitepage context, redirect
if(!isset($this)) {
    require_once(dirname(__FILE__). '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/submissionHistory');
}

$this->setFile(TEMPLATE_content, 'submission/submitOnline.html');
$this->setTab('submission');


//
// wat do on this page?
// Show a text entry, that's about it.
// If the submission is closed, then show the submitted version instead,
// otherwise show the most recent edited version.
// Same sort of permission as on edit?
// allow teachers to mark this?

$box_mapper        = new SubmissionBoxMapper();
$submission_mapper = Submission\FileMapper::getInstance();
$conf              = ConfigMapper::getInstance();

//
// Submission mapper - will get the most recent submission for this student.
$box_id      = $this->arg[0];
$sessionUser = $_SESSION['user'];

/** @var \Schoolbox\User $user */
if (isset($this->arg[1])) {
    // Teachers of this class may now impersonate handing in work as a student.
    $user = \Schoolbox\UserMapper::getInstance()->findById($this->arg[1]);
} else {
    $user = $sessionUser;
}

//
// Should be some permissions on whether a user is a member of group

$submission_box = $box_mapper->findById($this->arg[0]);
$dueDate        = new Alaress_Date_Format($submission_box->getDueDate(), 'Y-m-d');
$createdDate    = new Alaress_Date_Format($submission_box->getCreated(), 'Y-m-d');
$gradient       = $submission_box->getGradientIndex();


// <PERMS>

// has to allow submissions
if (!$submission_box->getAcceptOnline()) {
    error(19);
}

// If impersonating a student, I have to have write access to this page
if ($user->getId() != $sessionUser->getId()) {
    $studentRetHandler = new SubmissionReturn();
    // This may exit

    if (!$submission_box->hasAccess($sessionUser, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE)) {
        error(25);
    }
}

// if not multi, check there isn't already a (non-draft) submission
if (!$submission_box->getAcceptMultiple()) {
    $submitCount = $submission_mapper->findByColumnList([
        'draft'  => 0,
        'owner'  => $user->getId(),
        'box_id' => $submission_box->getId()

    ])->count();

    if ($submitCount) {
        error(19);
    }
}

// get a list of folders the user has perms to (yes... handlers... -_-)
$groupHandler = new Group;

$categoryList = $groupHandler->involvedInCategoryList($user->getId());

$categoryIDList = [];

foreach ($categoryList as $category) {
    $categoryIDList[] = $category['id'];
}

// if the user doesn't have folder perm, ignore everything we just calculated above and smother the block
if (!in_array($submission_box->getCategory()->getId(), $categoryIDList)) {
    error(19);
}

// </PERMS>


//
// Find most recent submissions for this user - later, consider showing drafts
// across side of screen.
$drafts = $submission_mapper->getDrafts($user->getId(), $box_id, 10);

if ($drafts->count()) {
    /** @var Submission\File $draft */
    $draft = $drafts->first();
    $body  = $draft->getStorage()->read();
} else {
    $body  = '';
}

$this->setBlock(TEMPLATE_content, 'confirm_terms', 'confirm_terms_block');
$this->setBlock(TEMPLATE_content, 'submit_on_behalf', 'submit_on_behalf_block');
$this->setVar('confirm_terms_block', '');
$this->setVar('submit_on_behalf_block', '');
$policy = $conf->get('lms_student_policy');
if (strlen(trim($policy)) > 0) {
    $this->setVar('STUDENT_SUBMIT_TERMS', Scrub::html($policy));
    $this->parse('confirm_terms_block', 'confirm_terms');
}
if ($user->getId() != $sessionUser->getId()) {
    $this->parse('submit_on_behalf_block', 'submit_on_behalf');
}
unset($html2text, $policy);

$zone = new \Schoolbox\Resource\Attachment\Zone('submission-file');
$zone->setAttachPrompt('Attach files')
     ->setAttachmentsLabel('')
     ->setMulti(true)
     ->setMimeFilter('*/*')
     ->setModalTitle('Select file');

$zone->renderIntoSitePage($this, 'ATTACH_ZONE');


$this->setVar(array(
    'SUBMISSION_USER_NAME'        => Scrub::text($user->formatName()),
    'SUBMISSION_USER_ID'          => Scrub::text($user->getId()),
    'SUBMISSION_USER_EXTERNAL_ID' => Scrub::text($user->getExternalId()),
    'FOLDER_NAME'                 => Scrub::text($submission_box->getCategory()->getName()),
    'FOLDER_ID'                   => $submission_box->getCategory()->getId(),
    'BOX_DUEDATE'                 => $dueDate->getRelativeTime(),
    'BOX_DUETIME'                 => $dueDate->isAtEndOfDay() ? $dueDate->getDateOnly() : $dueDate->getShort(),
    'BOX_DUEDATE_GRADIENT'        => $gradient,
    'BOX_ID'                      => $submission_box->getId(),
    'SUBMISSION_ID'               => null,
    'BOX_NAME'                    => Scrub::text($submission_box->getName()),
    'BOX_TYPE'                    => $submission_box->getWorkType()->getName(),
    'BOX_CREATED'                 => $createdDate->getRelativeTime(),
    'TEXTBOX_BODY'                => $body,
));

$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => 'Submissions',
        'href'  => '/submission/dueWork',
        'class' => ''
    ],
    [
        'text'  => $submission_box->getCategory()->getName(),
        'href'  => "/homepage/{$submission_box->getCategory()->getId()}",
        'class' => ''
    ],
    [
        'text'  => $submission_box->getName(),
        'href'  => '#',
        'class' => 'active'
    ],
]);
