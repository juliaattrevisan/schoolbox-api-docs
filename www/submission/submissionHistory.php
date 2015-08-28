<?php

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component;
use Schoolbox\Config\ConfigMapper;

// not in sitepage context, redirect
if (!isset($this)) {
    require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/submissionHistory');
}

/** @var SitePage $this */

$this->setFile(TEMPLATE_content, 'submission/submissionHistory.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock(TEMPLATE_content, 'action_publish', 'action_publish_block');
$this->setBlock(TEMPLATE_content, 'recent_submission', 'recent_submission_block');
$this->setBlock(TEMPLATE_content, 'confirm_terms', 'confirm_terms_block');
$this->setBlock(TEMPLATE_content, 'show_owner', 'show_owner_block');
$this->setBlock(TEMPLATE_content, 'returned_work', 'returned_work_block');
$this->setBlock(TEMPLATE_content, 'rubric', 'rubric_block');
$this->setBlock(TEMPLATE_content, 'action_submit', 'action_submit_block');
$this->setBlock(TEMPLATE_content, 'attachments', 'attachments_block');
$this->setBlock('returned_work', 'return_file', 'return_file_block');
$this->setBlock('return_file', 'return_file_uuid', 'return_file_uuid_block');
$this->setBlock('recent_submission', 'plag_score', 'plag_score_block');
$this->setBlock('recent_submission', 'recent_submission_download', 'recent_submission_download_block');
$this->setBlock('subject', 'work', 'work_block');
$this->setBlock('subject', 'teacher', 'teacher_block');
$this->setBlock('attachments', 'download', 'download_block');
$this->setBlock('attachments', 'legacy_download', 'legacy_download_block');

// Blocks for online editor buttons

// the whole manual block setting idea is entirely overrated...
$historyActivityTypes = array('submitted', 'returned', 'marked', 'extended');
foreach ($historyActivityTypes as $historyType) {
    $this->setBlock('work', "history_$historyType", "history_{$historyType}_block");
    $this->setVar("history_{$historyType}_block", '');
}

$this->setBlock('work', 'work_grade', 'work_grade_block');
$this->setBlock('history_marked', 'history_marked_hascomment', 'history_marked_hascomment_block');

$this->setVar([
    'teacher_block'       => '',
    'action_submit_block' => '',
    'STATUS_HEALTH'       => 'none',
    'STATUS_LABEL'        => '',
]);

if ($this->arg[0] == false) {
    error(2);
}

$boxMapper        = new Component\SubmissionBoxMapper();
$submissionMapper = new Component\SubmissionBox\Submission\FileMapper();
$work_mapper      = new Component\SubmissionBox\WorkTypeMapper();
$return_mapper    = new Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
$folderMapper     = new \Schoolbox\Resource\Folder\Mapper();
$conf             = ConfigMapper::getInstance();

$userHandler       = new User;
$groupHandler      = new Group;
$submissionHandler = new Submission;
$returnHandler     = new SubmissionReturn;
$allowedHandler    = new AllowedFile;

$currentUserId = $_SESSION['id'];

// if no second argument supplied and student, assume current user rather than just dying
if ($this->arg[1] == false && $groupHandler->isStudent()) {
    SitePage::localRedir("/submission/submissionHistory/{$this->arg[0]}/{$currentUserId}");
}

if (is_numeric($this->arg[1])
    && ($groupHandler->isParentOfChild($this->arg[1])
        || $groupHandler->isStaff()
        || $currentUserId == $this->arg[1]
    )
) {
    $userID = $this->arg[1];
} else {
    //displaying users on due work, so log this as the last time they viewed due work
    // logger, level, event, message, extras = array()
//    Logger::log('info', 'view_due_work');
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $currentUserId;
}

// setup boolean for whether the current user is a parent and ONLY a parent (and therefore can't see download links)
// error out if parent and student submissions restricted
$parentOnly = $groupHandler->isParent()
    && !$groupHandler->isStaff()
    && !$groupHandler->isSuperuser();
if ($parentOnly && $conf->get('restrict_student_submissions')) {
    error(19);
}

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
    error(2);
}


if ($user['synergy_id'] || $user['synergy_id'] === 0) {
    $categoryList = $groupHandler->involvedInCategoryList($user['uid']);
} else {
    $categoryList = array();
}

$categoryIDList = array();

if (is_array($categoryList)) {
    foreach ($categoryList as $category) {
        $categoryIDList[] = $category['id'];
    }
}

/* Submission Box Details
 */

try {
    $box = $boxMapper->findById($this->arg[0]);
} catch (Schoolbox\Mapper\Exception $e) {
    error(17);
    exit;
}

$is_mark_shown = true;
if ($groupHandler->isStudent() && !$box->isMarkShownToStudents()) {
    $is_mark_shown = false;
} elseif ($parentOnly && !$box->isMarkShownToParents()) {
    $is_mark_shown = false;
}
$owner = $userHandler->getByID($box->getOwner()->getId());

$this->setVar(
    array(
        'OWNER_ID'    => $owner['uid'],
        'OWNER_NAME'  => $userHandler->formatName($owner),
        'OWNER_THUMB' => "/portrait?userId={$owner['synergy_id']}&size=square32"
    )
);

$externalClassHandler = new External_Class();

$folder = $folderMapper->findById($box->getCategory()->getId());

if ($folder->getCodes()->count()) {
/* Get all the folder codes, implode them into a csv
     * as per the handler
     */
    $temp = array();

    foreach ($folder->getCodes() as $currentCode) {
        $temp[] = $currentCode->getCode();
    }

    $classCode = implode(', ', $temp);

    /* Iterate
     */
    $listSyns    = $externalClassHandler->getTeacherName($classCode);
    $staffGroups = $groupHandler->getStaffGroupTypeIds();

    if (is_array($listSyns)) {
        $emailObj = new Email();
        foreach ($listSyns as $synid) {
            $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
            $meta    = $userHandler->getByID($teacher['uid']);

            $this->setVar(
                array(
                    'TEACHER_ID'           => $teacher['uid'],
                    'TEACHER_NAME'         => $userHandler->formatName($meta),
                    'TEACHER_EXTERNAL_ID'  => $synid,
                    'TEACHER_GROUP'        => Scrub::text($meta['gname']),
                    'TEACHER_AVATAR'       => "/portrait?userId=$synid&size=square64",
                    'TEACHER_AVATAR_TITLE' => $userHandler->formatName($meta),
                    'TEACHER_URL'          => $_SESSION['mailhandle'] . $emailObj->formatEmailFromArray($teacher),
                )
            );

            $this->parse('teacher_block', 'teacher', true);
        }
    }
}

$dueDate     = new Alaress_Date_Format($box->getDueDate(), 'Y-m-d');
$createdDate = new Alaress_Date_Format($box->getCreated(), 'Y-m-d');

$submission_history = $submissionMapper->getHistory($userID, $this->arg[0]);

if ($is_mark_shown) {
    // Filter out items with a mark, instead of showing this way?
    // Returns probably should not show at all if marks aren't shown?
    $submission_history->merge($return_mapper->getHistory($userID, $this->arg[0]));
}
$submission_history->sortBy('date', 'desc');

$gradient = $box->getGradientIndex();

$this->setVar(
    array(
        'CATEGORY_NAME'        => Scrub::text($box->getCategory()->getName()),
        'CATEGORY_CODE'        => $classCode,
        'CATEGORY_ID'          => $box->getCategory()->getId(),
        'CATEGORY_THUMB'       => '',
        'BOX_DUEDATE'          => $dueDate->getRelativeTime(),
        'BOX_DUETIME'          => $dueDate->isAtEndOfDay() ? $dueDate->getDateOnly() : $dueDate->getShort(),
        'BOX_DUEDATE_GRADIENT' => $gradient,
        'BOX_ID'               => $box->getId(),
        'BOX_NAME'             => Scrub::text($box->getName()),
        'BOX_OWNER'            => $userHandler->formatName($user),
        'BOX_OWNER_ID'         => $userID,
        'BOX_DESCRIPTION'      => Scrub::html($box->getDescription()),
        'BOX_TYPE'             => $work_mapper->getNameFromId($box->getWorkType()->getId()),
        'BOX_CREATED'          => $createdDate->getRelativeTime()
    )
);

// determine whether to display the name of the user we're looking at
if ($userID == $currentUserId) {
    $this->setVar('show_owner_block', '');
} else {
    $this->parse('show_owner_block', 'show_owner');
}

/* Check whether a file is associated with the work
 */
$attachments = $box->getAttachments();

if ($attachments->count() > 0) {
    foreach ($attachments as $attachment) {
        $this->setVar(
            array(
                'FILE_HASH'  => $attachment->getHash(),
                'FILE_THUMB' => $attachment->getMimetype()->getIconFile(),
                'FILE_SIZE'  => Alaress_Byte_Format::bytesToString($attachment->getSize()),
                'FILE_NAME'  => $attachment->getFilename(),
                'FILE_DATE'  => $attachment->getCreatedAt()->getLong()
            )
        );
        $this->parse('download_block', 'download', true);
    }
    $this->parse('attachments_block', 'attachments');
} else {
    $this->setVar('attachments_block', '');
}

// set the teacher details
$teacher = $userHandler->getByID($box->getOwner()->getId());

$teacher_details = array(
    'TEACHER_ID'    => $teacher['uid'],
    'TEACHER_NAME'  => $userHandler->formatName($teacher),
    'TEACHER_THUMB' => "/portrait?userId={$teacher['synergy_id']}&size=square64",
);
$this->setVar($teacher_details);


$usersList = array();
$dem    = DueWork_ErrorMapper::getInstance();
$errors = $dem->listDisplayValues();

$recent_activity = null;
$recent_return     = null;
if (count($submission_history)) {
    $j = 0;

    foreach ($submission_history as $activity_item) {
        /** @var Activity $activity_item */
        // reset blocks
        foreach ($historyActivityTypes as $historyType) {
            $this->setVar("history_{$historyType}_block", '');
        }
        $this->setVar(
            array(
                'history_marked_hascomment_block' => '',
                'history_returned_block'          => '',
                'work_grade_block'                => ''
            )
        );

        $workDate = new Alaress_Date_Format($activity_item->getDate(), 'U');

        // if submission item
        $mark      = '';
        $norm_mark = 0;
        $gradient  = 0;
        $file_id   = 0;

        //
        // User details: default to teacher.
        $owner   = $teacher_details['TEACHER_ID'];
        $history = array(
            'HISTORY_CLASS'                => null,
            'HISTORY_ICON'                 => null,
            'HISTORY_GRADIENT'             => 0,
            'HISTORY_DATETIME'             => $workDate->getRelativeTime(),
            'HISTORY_MARK'                 => '',
            'HISTORY_COMMENT'              => null,
            'HISTORY_SUBMISSION_FILE_ID'   => null,
            'HISTORY_SUBMISSION_FILE_NAME' => null,
            'HISTORY_RETURN_FILE_ID'       => null,
            'HAS_GRADE'                    => '',
        );
        if ($activity_item->isSubmission()) {
            $history['HISTORY_CLASS'] = 'submit';
            $history['HISTORY_ICON']  = 'up-arrow';

            $submission = $activity_item->getSubmission();

            // submission file ID, submission file name
            if ($submission instanceof Component\SubmissionBox\Submission\File) {
                $history['HISTORY_SUBMISSION_FILE_ID']   = $submission->getId();
                $history['HISTORY_SUBMISSION_FILE_NAME'] = $submission->getFilename();
            }

            // Name, icon and ID of user: use student's
            $owner = $submission->getOwner()->getId();

        } elseif ($activity_item->isReturn()) {
            // if return item
            if ($activity_item->getReturnedWork()->getFilename()) {
                // work returned
                $history['HISTORY_CLASS'] = 'return';
                $history['HISTORY_ICON']  = 'submitted';
            } else {
                // mark given
                $history['HISTORY_CLASS'] = 'mark';
                $history['HISTORY_ICON']  = 'tick';
            }

            $mark      = $activity_item->getReturnedWork()->getMark();
            $norm_mark = $activity_item->getReturnedWork()->getNormalizedMark();

            if ($box->getMarkType()->getId() == Component\SubmissionBox\MarkType::TYPE_LETTER
                || in_array($mark, $errors)
            ) {
                $mark = $returnHandler->shortenGrade($mark);
            } else {
                $mark = Scrub::Text($mark);
            }
            $history['HISTORY_MARK']     = $mark;
            $history['HAS_GRADE']        = ($mark || $mark === 0) ? 'grade' : '';
            $history['HISTORY_COMMENT']  = Scrub::text($activity_item->getReturnedWork()->getComment());
            $history['HISTORY_GRADIENT'] = $activity_item->getReturnedWork()->getGradientFromNormalizedMark();

            //
            // submission file ID, submission file name
            $history['HISTORY_RETURN_FILE_ID']   = $activity_item->getReturnedWork()->getId();
            $history['HISTORY_RETURN_FILE_NAME'] = Scrub::text($activity_item->getReturnedWork()->getFilename());

            $owner = $activity_item->getReturnedWork()->getMarker()->getId();
        } else {
            // otherwise extension
            $history['HISTORY_CLASS'] = 'extend';
            $history['HISTORY_ICON']  = 'clock';
        }
        $this->setVar($history);

        // if the user in question has not previously been seen, retrieve
        if (!isset($usersList[$owner])) {
            $usersList[$owner] = $userHandler->getByID($owner);
        }

        // [owner] is contextually set to either the student or teacher, depending on whether it's submit or return
        $this->setVar(
            array(
                'HISTORY_USER_ID'    => $owner,
                'HISTORY_USER_NAME'  => $userHandler->formatName($usersList[$owner]),
                'HISTORY_USER_THUMB' => "/portrait?userId={$usersList[$owner]['synergy_id']}&size=square64"
            )
        );

        // submission by student
        if ($activity_item->isSubmission()) {
            // if you are a parent (and only a parent), you can only see plaintext, not a link
            if ($parentOnly) {
                $this->setVar('history_submitted_block', 'submitted work');
            } else {
                $this->parse('history_submitted_block', 'history_submitted');
            }

            // Remember most recent submission
            if (!isset($recent_activity)) {
                $recent_activity = $activity_item;
            }

        }
        // return by teacher
        $parentsCanSee = !($conf->get('restrict_parent_grades') && $groupHandler->isParent());
        $isSuperuser   = $groupHandler->isSuperuser();

        if ($activity_item->isReturn()) {
            if ($isSuperuser || $parentsCanSee) {
                if ($history['HISTORY_COMMENT']) {
                    $this->parse('history_marked_hascomment_block', 'history_marked_hascomment');
                }

                // file return rather than mark
                if ($history['HISTORY_RETURN_FILE_NAME']) {
                    if ($parentOnly) {
                        $this->setVar('history_returned_block', 'returned work');
                    } else {
                        $this->parse('history_returned_block', 'history_returned');
                    }
                }

                $this->parse('work_grade_block', 'work_grade');
            } else {
                /* For styling reasons
                 */
                $this->setVar(
                    array(
                        'HAS_GRADE' => ''
                    )
                );
            }

            $this->parse('history_marked_block', 'history_marked');

            /* Remember the most recent returned work, because we need it
             * later on
             */
            if (!isset($recent_return)) {
                $recent_return = $activity_item;
            }
        }

        $this->parse('work_block', 'work', true);

        $this->setVar('status_due_block', '');
        $this->setVar('status_awaiting_review_block', '');
        $this->setVar('status_graded_block', '');

        $j++;
    }

    $this->parse('subject_block', 'subject');
} else {
    $this->setVar([
        'work_block'    => '',
        'STATUS_HEALTH' => 'unsubmitted',
        'STATUS_LABEL'  => 'Not Submitted'
    ]);
    $this->parse('subject_block', 'subject');
}

if (isset($recent_activity)) {
    /* Show most recent submission
     * Hide Submit form
     * If multi-submit is enabled, show the Resubmit form
     * Find out whether the latest submission was overdue
     */

    // override the due gradient because we have submitted
    $this->setVar('BOX_DUEDATE_GRADIENT', 'black');

    if ($recent_activity->getDate()->getUnixTimestamp() > $box->getDueDate()->getUnixTimestamp()) {
        $this->setVar([
            'STATUS_HEALTH' => 'submitted-late',
            'STATUS_LABEL'  => 'Submitted Late',
        ]);
    } else {
        $this->setVar([
            'STATUS_HEALTH' => 'submitted',
            'STATUS_LABEL'  => 'Submitted on Time',
        ]);
    }

    $recentSubmissionDate = new Alaress_Date_Format($recent_activity->getDate(), 'U');

    // get filetype details
    $student_submission = $recent_activity->getSubmission();

    // populate details into page
    if ($student_submission instanceof Component\SubmissionBox\Submission\File) {
        $output = array(
            'MOST_RECENT_SUBMISSION_FILE_NAME'  => $student_submission->getFilename(),
            'MOST_RECENT_SUBMISSION_FILE_SIZE'  => Alaress_Byte_Format::bytesToString($student_submission->getSize()),
            'MOST_RECENT_SUBMISSION_FILE_THUMB' => $student_submission->getMimetype()->getIconFile() ?: 'txt.png',
            'MOST_RECENT_SUBMISSION_FILE_DATE'  => $recentSubmissionDate->getRelativeTime(),
            'MOST_RECENT_SUBMISSION_FILE_ID'    => $recent_activity->getSubmission()->getId(),
        );
    } else {
        $output = [];
    }

    $this->setVar($output);

    // if we are the owning student, display plagiarism details if they are available
    if (($userID == $currentUserId && $conf->get('plagscan_student_visible')) || $_SESSION['user']->isSuperuser()) {
        // retrieve plagiarism score, if available
        $docMapper = Plagscan_DocumentMapper::getInstance();
        $plagDoc   = null;
        try {
            /* @var $plagDoc Plagscan_Document */
            $plagDoc = $docMapper->findOneByColumn('file_id', $recent_activity->getSubmission()->getId());

            if ($plagDoc->getId() && $plagDoc->getStatus() == Plagscan_Document::STATUS_COMPLETE) {
                // what colour should the plagiarism text be, and what should the tooltip say?
                if ($plagDoc->getScore() > 0) {
                    $status  = 'plagscan-critical';
                    $tooltip = 'Your submission is extremely similar to other documents scanned.';
                } elseif ($plagDoc->getScore() > 20) {
                    $status  = 'plagscan-warning';
                    $tooltip = 'Your submission is somewhat similar to other documents scanned.';
                } else {
                    $status  = 'plagscan-ok';
                    $tooltip = 'Your submission is quite different to other documents scanned.';
                }

                $this->setVar(
                    array(
                        'MOST_RECENT_SUBMISSION_FILE_PLAG_CLASS' => $status,
                        'MOST_RECENT_SUBMISSION_FILE_PLAGSCORE'  => $plagDoc->getScore(),
                        'MOST_RECENT_SUBMISSION_FILE_PLAG_TITLE' => $tooltip,
                    )
                );
                $this->parse('plag_score_block', 'plag_score');
            } else {
                $this->setVar('plag_score_block', '');
            }
        } catch (Schoolbox\Mapper\Exception $e) {
            $this->setVar('plag_score_block', '');
        }
    } else {
        $this->setVar('plag_score_block', '');
    }

    // only allow a resubmission:
    // if the current user is the owner
    // if the submission box is accepting submissions
    // and if the current time is between open and late
    if (
        $userID == $currentUserId
        && $box->isOpen()
        && $box->getAcceptOnline()
        && $box->getAcceptMultiple()
    ) {
        $this->setVar('SUBMIT_LABEL', 'Resubmit');
        $this->parse('action_submit_block', 'action_submit');
    }

    // if parent, hide the "view submitted work" button
    if ($parentOnly) {
        $this->setVar('recent_submission_block', '');
    } else {
        $this->parse('recent_submission_download_block', 'recent_submission_download');
        $this->parse('recent_submission_block', 'recent_submission');
    }

} else {
    /* Hide most recent submission
     * Show Submit form
     * Hide the Resubmit form
     */
    if ($userID == $currentUserId && $box->isOpen() && $box->getAcceptOnline()) {
        // Which edit button gets shown (is there already a draft?)
        $drafts = $submissionMapper->getDrafts($userID, $box->getId(), 1);

        if ($drafts->count()) {
            $this->setVar([
                'DRAFT_MODIFIED' => $drafts->first()->getCreatedAt()->getLocalShort(),
                'STATUS_HEALTH'  => 'regular',
                'STATUS_LABEL'   => 'Draft Saved'
            ]);
        }

        $this->setVar('SUBMIT_LABEL', 'Submit');
        $this->parse('action_submit_block', 'action_submit');
    } else {
        $this->setVar('action_submit_block', '');
    }

    $this->setVar('recent_submission_block', '');

}

// if the user doesn't have folder perm, ignore everything we just calculated above and smother the block
if (!in_array($box->getCategory()->getId(), $categoryIDList)) {
    $this->setVar(
        array(
            'action_submit_block'   => '',
        )
    );
}

/* Detect returned work
 */
if (isset($recent_return) && !($conf->get('restrict_parent_grades') && $groupHandler->isParent())) {
    // get filetype details
    $return = $recent_return->getReturnedWork();

    $mark = $return->getMark();

    if ($box->getMarkType()->getId() == Component\SubmissionBox\MarkType::TYPE_LETTER
        || in_array($mark, $errors)
    ) {
        $mark = $returnHandler->shortenGrade($mark);
    } else {
        $mark = Scrub::Text($mark);
    }

    $this->setVar(
        array(
            'RETURNED_WORK_OWNER_ID'   => $return->getOwner()->getId(),
            'RETURNED_WORK_COMMENT'    => Scrub::Text($return->getComment()),
            'RETURNED_WORK_MARK'       => $mark,
            'RETURNED_WORK_MARK_TIP'   => $mark == $return->getMark() ? '' : $return->getMark(),
            'RETURNED_WORK_GRADIENT'   => $return->getGradientFromNormalizedMark(),
            'STATUS_HEALTH'            => 'ok',
            'STATUS_LABEL'             => 'Marked'
        ));

    // Just zero these so they don't appear
    $this->setVar(
        array(
            'RETURNED_WORK_SUBMISSION_ID' => '',
            'return_file_uuid_block'      => '',
        )
    );

    if($return->getStorage()->getHash() && !$parentOnly) {
        try{

            $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($return->getStorage()->getHash());

            $this->setVar(
                array(
                    'RETURNED_WORK_FILE_ID'    => $return->getId(),
                    'RETURNED_WORK_FILE_SIZE'  => Alaress_Byte_Format::bytesToString($storage->getSize()),
                    'RETURNED_WORK_FILE_NAME'  => Scrub::Text($storage->getFilename()),
                    'RETURNED_WORK_FILE_DATE'  => $return->getReturned()->getRelativeTime(),
                    'RETURNED_WORK_FILE_THUMB' => $storage->getMimetype()->getIconFile() ?: 'txt.png',
                )
            );
            $this->parse('return_file_block', 'return_file');

        } catch (Schoolbox\Mapper\Exception $e) {
            $this->setVar('return_file_block', '');
        }
    }
    else {
        $this->setVar('return_file_block', '');
    }

    /* If there's a crocodoc document, display a link to it.
     * Do this even if there's no returned file in storage.
     */
    if ($return->getUuid()) {
        try {
            $associatedSubmission = $submissionMapper->findOneByColumn('uuid', $return->getUuid());

            $this->setVar(
                array(
                    'RETURNED_WORK_SUBMISSION_ID' => $associatedSubmission->getId(),
                )
            );
        } catch (\Exception $e) {
            error(44);
        }

        $this->parse('return_file_uuid_block', 'return_file_uuid');
    }

    $this->parse('returned_work_block', 'returned_work');

} else {
    $this->setVar('returned_work_block', '');
}

// do fun rubric things
if ($box->isRubric()) {
    $rb = new DueWork_Rubric_Lightbox($this);
    $rb->render('rubric_block', $box->getId(), $user['uid'], $box->canViewRubric($_SESSION['user']), false);
} else {
    $this->setVar('rubric_block', '');
}


/* Only offer a publish button if there is a submission history OR a return history
 * and if the submission box isn't already published for this user
 */
$showcaseMapper = new \Eportfolio\ShowcaseMapper();

$existingShowcasedItem = $showcaseMapper->findByColumnList(
    array(
        'uid'               => $user['uid'],
        'submission_box_id' => $box->getId()
    )
);

/* If it has either a recent submission or recent return, and hasn't been published,
 * allow it to be published
 */
if ($user['eportfolio_enabled'] && (isset($recent_activity) || isset($recent_return))) {
    if (!$existingShowcasedItem->count()) {
        $this->setVar(
            array(
                'PUBLISH_TYPE'    => 'boxId',
                'PUBLISH_ID'      => $box->getId(),
                'PUBLISH_USER_ID' => $user['uid'],
                'PUBLISH_ACTION'  => 'publish',
                'PUBLISH_BUTTON'  => 'Publish to ePortfolio'
            )
        );
    } else {
        $this->setVar(
            array(
                'PUBLISH_TYPE'    => 'showcaseItemId',
                'PUBLISH_ID'      => $existingShowcasedItem->first()->getId(),
                'PUBLISH_USER_ID' => $user['uid'],
                'PUBLISH_ACTION'  => 'unpublish',
                'PUBLISH_BUTTON'  => 'Remove from ePortfolio'
            )
        );
    }

    $this->parse('action_publish_block', 'action_publish');
} else {
    $this->setVar('action_publish_block', '');
}