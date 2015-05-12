<?php

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;
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
$user_id = $_SESSION['id'];
$box_id  = $this->arg[0];

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

// if not multi, check there isn't already a (non-draft) submission
if (!$submission_box->getAcceptMultiple()) {
    $submitCount = $submission_mapper->findByColumnList([
        'draft'  => 0,
        'owner'  => $user_id,
        'box_id' => $submission_box->getId()

    ])->count();

    if ($submitCount) {
        error(19);
    }
}

// get a list of folders the user has perms to (yes... handlers... -_-)
$groupHandler = new Group;
$userHandler  = new User;
$user         = $userHandler->getByID($user_id);

if ($user['synergy_id'] || $user['synergy_id'] === 0) {
    $categoryList = $groupHandler->involvedInCategoryList($user['uid']);
} else {
    $categoryList = [];
}

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
$drafts = $submission_mapper->getDrafts($user_id, $box_id, 10);

if ($drafts->count()) {
    /** @var Submission\File $draft */
    $draft = $drafts->first();
    $body  = $draft->getStorage()->read();
} else {
    $body  = '';
}

$this->setBlock(TEMPLATE_content, 'confirm_terms', 'confirm_terms_block');
$policy    = $conf->get('lms_student_policy');
$html2text = new \Html2Text\Html2Text($policy);
// Ensure that a policy that contains ONLY an image still displays,
// by allowing img tags through unmolested
$html2text->set_allowed_tags('<img>');
if (strlen(trim($html2text->get_text())) > 0) {
    $this->setVar('STUDENT_SUBMIT_TERMS', Scrub::html($policy));
    $this->parse('confirm_terms_block', 'confirm_terms');
} else {
    $this->setVar('confirm_terms_block', '');
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
    'CATEGORY_NAME' => Scrub::text($submission_box->getCategory()->getName()),

    'BOX_DUEDATE'          => $dueDate->getRelativeTime(),
    'BOX_DUETIME'          => $dueDate->isAtEndOfDay() ? $dueDate->getDateOnly() : $dueDate->getShort(),    'BOX_DUEDATE_GRADIENT' => $gradient,

    'BOX_ID'        => $submission_box->getId(),
    'SUBMISSION_ID' => null,
    'BOX_NAME'      => Scrub::text($submission_box->getName()),
    'BOX_TYPE'      => $submission_box->getWorkType()->getName(),
    'BOX_CREATED'   => $createdDate->getRelativeTime(),
    'TEXTBOX_BODY'  => $body,
));
