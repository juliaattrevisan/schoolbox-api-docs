<?php

// not in sitepage context, redirect
if(!isset($this)) {
    require_once(dirname(__FILE__). '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/submissionHistory');
}

$this->setFile(TEMPLATE_content, 'submission/submissionHistory.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock(TEMPLATE_content, 'download', 'download_block');
$this->setBlock(TEMPLATE_content, 'legacy_download', 'legacy_download_block');
$this->setBlock(TEMPLATE_content, 'action_submit', 'action_submit_block');
$this->setBlock(TEMPLATE_content, 'action_publish', 'action_publish_block');
$this->setBlock(TEMPLATE_content, 'recent_submission', 'recent_submission_block');
$this->setBlock(TEMPLATE_content, 'show_owner', 'show_owner_block');
$this->setBlock(TEMPLATE_content, 'returned_work', 'returned_work_block');
$this->setBlock(TEMPLATE_content, 'teacher', 'teacher_block');
$this->setBlock(TEMPLATE_content, 'rubric', 'rubric_block');
$this->setBlock('returned_work', 'return_file','return_file_block');
$this->setBlock('recent_submission', 'plag_score', 'plag_score_block');
$this->setBlock('recent_submission', 'overdue', 'overdue_block');
$this->setBlock('recent_submission', 'action_resubmit', 'action_resubmit_block');
$this->setBlock('recent_submission', 'recent_submission_download', 'recent_submission_download_block');
$this->setBlock('subject', 'work', 'work_block');

// the whole manual block setting idea is entirely overrated...
$historyActivityTypes = array('submitted', 'returned', 'marked', 'extended');
foreach($historyActivityTypes as $historyType) {
    $this->setBlock('work', "history_$historyType", "history_{$historyType}_block");
    $this->setVar("history_{$historyType}_block", '');
}

$this->setBlock('work', 'work_grade', 'work_grade_block');
$this->setBlock('history_marked', 'history_marked_hascomment', 'history_marked_hascomment_block');
$this->setVar('teacher_block', '');

use Schoolbox\Assessment\Component;
$mapper = new Component\SubmissionBoxMapper();
$work_mapper = new Component\SubmissionBox\WorkTypeMapper();

$userHandler = new User;
$groupHandler = new Group;
$submissionHandler = new Submission;
$returnHandler = new SubmissionReturn;
$allowedHandler = new AllowedFile;

if (is_numeric($this->arg[1]) && ($groupHandler->isParentOfChild($this->arg[1]) || $groupHandler->isStaff() || $_SESSION['id'] == $this->arg[1])) {
	$userID = $this->arg[1];
}
else {
    //displaying users on due work, so log this as the last time they viewed due work
	// logger, level, event, message, extras = array()
//	Logger::log('info', 'view_due_work');
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

/* Check if system config restrict_student_submissions is on
 */
if ($groupHandler->isParentOfChild($this->arg[1])) {
	if (Cfg::get('restrict_student_submissions')) {
		error(19);
	}
}

// setup boolean for whether the current user is a parent and ONLY a parent (and therefore can't see download links)
$parentOnly = ($groupHandler->isParent() && !$groupHandler->isStaff() && !$groupHandler->isSuperuser());

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}


if($user['synergy_id'] || $user['synergy_id'] === 0) {
    $categoryList = $groupHandler->involvedInCategoryList($user['uid']);
}

$categoryIDList = array();
if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

/* Submission Box Details
 */
$submission = $mapper->findById($this->arg[0]);
$is_mark_shown = true;
if ($groupHandler->isStudent() && !$submission->isMarkShownToStudents()) {
	$is_mark_shown = false;
}
elseif ($parentOnly && !$submission->isMarkShownToParents()) {
	$is_mark_shown = false;
}
$owner = $userHandler->getByID($submission->getOwner()->getId());

$this->setVar(array(
    'OWNER_ID' => $owner['uid'],
    'OWNER_NAME' => $userHandler->formatName($owner),
    'OWNER_THUMB' => "/portrait?userId={$owner['synergy_id']}&size=square32"
));

$externalClassHandler = new External_Class();
$userHandler = new User();
$groupHandler = new Group();
$catHandler = new Category();

$codes = $catHandler->getCodeForCategory($submission->getCategory()->getId());

if($codes && count($codes)) {
    $classCode = $codes['codes'];

    $listSyns = $externalClassHandler->getTeacherName($classCode);
    $staffGroups = $groupHandler->getStaffGroupTypeIds();

    if(is_array($listSyns)) {
        foreach($listSyns as $synid) {
            $teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);
            $meta = $userHandler->getByID($teacher['uid']);

            $this->setVar(array(
                'TEACHER_ID' => $teacher['uid'],
                'TEACHER_NAME' => $userHandler->formatName($meta),
                'TEACHER_EXTERNAL_ID' => $synid,
                'TEACHER_GROUP' => $meta['gname'],
                'TEACHER_THUMB' => "/portrait?userId=$synid&size=square32"
            ));

            $this->parse('teacher_block','teacher', true);
        }
    }
}

$dueDate = new Alaress_Date_Relative($submission->getDueDate(),'y-m-d');
$createdDate = new Alaress_Date_Relative($submission->getCreated(), 'y-m-d');

$submit_mapper = new Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
$submission_history = $submit_mapper->getSubmissionHistory($userID, $this->arg[0]);
if ($is_mark_shown) {
	$return_mapper = new Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
	//
	// Filter out items with a mark, instead of showing this way?
	// Returns probably should not show at all if marks aren't shown?
	$submission_history->merge($return_mapper->getSubmissionHistory($userID, $this->arg[0]));
}
$submission_history->sortBy('date', 'desc');

$urgency = ($dueDate->getUnixTimestamp() - time()) / 86400;
$gradient = max(0, min(10, $urgency));

$this->setVar(array(
    'CATEGORY_NAME' => Scrub::text($submission->getCategory()->getName()),
    'CATEGORY_ID'   => $submission->getCategory()->getId(),
    'CATEGORY_THUMB' => '',

    'BOX_DUEDATE' => $dueDate->getRelativeTime(),
    'BOX_DUEDATE_GRADIENT' => $gradient,

    'BOX_ID' => $submission->getId(),
    'BOX_NAME' => Scrub::text($submission->getName()),
    'BOX_OWNER' => $userHandler->formatName($user),
    'BOX_DESCRIPTION' => Scrub::text($submission->getDescription()),
    'BOX_TYPE' => $work_mapper->getNameFromId($submission->getWorkType()->getId()),
    'BOX_CREATED' => $createdDate->getRelativeTime()
));

// determine whether to display the name of the user we're looking at
if($userID == $_SESSION['id']) {
    $this->setVar('show_owner_block', '');
}
else {
    $this->parse('show_owner_block', 'show_owner');
}

/* Check whether a file is associated with the work
 */
$legacy_file_id = $submission->getLegacyFileId();
$file_links     = $submission->getFileLinks();
if ($file_links->count() > 0) {
    foreach ($file_links as $file_link) {
        $file = $file_link->getFile();
        $this->setVar(array(
            'FILE_HASH'  => $file->getHash(),
            'FILE_THUMB' => $file->getIconFromFilename(),
            'FILE_SIZE'  => Alaress_Byte_Format::bytesToString($file->getFilesize()),
            'FILE_NAME'  => $file->getFilename(),
            'FILE_DATE'  => $file->getUploadedOn()->getUsingFormat('jS M Y, g:ia')
        ));
        $this->parse('download_block', 'download', true);
    }
    $this->setVar('legacy_download_block', '');
}
elseif ($legacy_file_id && is_numeric($legacy_file_id)) {
    $fileHandler = new File;
    $file = $fileHandler->getByID($legacy_file_id);

    $this->setVar(array(
        'FILE_ID'    => $legacy_file_id,
        'FILE_THUMB' => $file['iconFile'],
        'FILE_SIZE'  => Alaress_Byte_Format::bytesToString($file['file_size']),
        'FILE_NAME'  => $file['realname'],
        'FILE_DATE'  => date('jS M Y, g:ia', $file['submitted'])
    ));
    $this->parse('legacy_download_block', 'legacy_download');
    $this->setVar('download_block', '');
}
else {
    $this->setVar('legacy_download_block', '');
    $this->setVar('download_block', '');
}

// set the teacher details
$teacher = $userHandler->getByID($submission->getOwner()->getId());
$teacher_details = array(
	'TEACHER_ID'    => $teacher['uid'],
	'TEACHER_NAME'  => $userHandler->formatName($teacher),
	'TEACHER_THUMB' => "/portrait?userId={$teacher['synergy_id']}&size=square32",
);
$this->setVar($teacher_details);

$usersList = array();


$recent_submission = null;
$recent_return     = null;
if (count($submission_history)) {
	$j = 0;

	/* Make submit button available/unavailable depending on submissions and
	 * returns from $allWork
	 */
	$this->setVar('action_submit_block', '');

    $dem = DueWork_ErrorMapper::getInstance();
    $errors = $dem->listDisplayValues();

	foreach ($submission_history as $activity_item) {

        // reset blocks
        foreach($historyActivityTypes as $historyType) {
            $this->setVar("history_{$historyType}_block", '');
        }
        $this->setVar(array(
            'history_marked_hascomment_block' => '',
            'history_returned_block' => '',
            'work_grade_block' => ''
        ));

        $workDate = new Alaress_Date_Relative($activity_item->getDate(), 'U');

        // if submission item
		$mark      = '';
		$norm_mark = 0;
		$gradient  = 0;
		$file_id   = 0;

		//
		// User details: default to teacher.
		$owner   = $teacher_details['TEACHER_ID'];
		$history = array(
			'HISTORY_CLASS'    => null,
			'HISTORY_ICON'     => null,
			'HISTORY_GRADIENT' => 0,
			'HISTORY_DATETIME' => $workDate->getRelativeTime(),
			'HISTORY_MARK'     => '',
			'HISTORY_COMMENT'  => null,
			'HISTORY_FILE_ID'  => $submission->getLegacyFileId(),
			'HISTORY_SUBMISSION_FILE_ID'   => null,
			'HISTORY_SUBMISSION_FILE_NAME' => null,
			'HISTORY_RETURN_FILE_ID'       => null,
			'HAS_GRADE'                    => '',
		);
        if ($activity_item->isSubmission()) {
	        $history['HISTORY_CLASS'] = 'submit';
	        $history['HISTORY_ICON']  = 'up-arrow';

	        //
	        // submission file ID, submission file name
	        $history['HISTORY_SUBMISSION_FILE_ID']   = $activity_item->getSubmission()->getId();
	        $history['HISTORY_SUBMISSION_FILE_NAME'] = $activity_item->getSubmission()->getFilename();

	        //
	        // Name, icon and ID of user: use student's
	        $owner = $activity_item->getSubmission()->getOwner()->getId();
        }
        // if return item
        elseif ($activity_item->isReturn()) {
            // work returned
            if ($activity_item->getReturnedWork()->getFilename()) {
	            $history['HISTORY_CLASS'] = 'return';
	            $history['HISTORY_ICON']  = 'submitted';
            }
            // mark given
            else {
	            $history['HISTORY_CLASS'] = 'mark';
	            $history['HISTORY_ICON']  = 'tick';
            }

	        $mark      = $activity_item->getReturnedWork()->getMark();
	        $norm_mark = $activity_item->getReturnedWork()->getNormalizedMark();

	        if ($submission->getMarkType()->getId() == Component\SubmissionBox\MarkType::TYPE_LETTER
		        || in_array($mark, $errors)
	        ) {
		        $mark = $returnHandler->shortenGrade($mark);
	        }
	        else {
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

	        $owner = $activity_item->getSubmissionBox()->getOwner()->getId();
        }
        // otherwise extension
        else {
	        $history['HISTORY_CLASS'] = 'extend';
	        $history['HISTORY_ICON']  = 'clock';
        }
		$this->setVar($history);

         // if the user in question has not previously been seen, retrieve
        if(!isset($usersList[$owner])) {
            $usersList[$owner] = $userHandler->getByID($owner);
        }

        // [owner] is contextually set to either the student or teacher, depending on whether it's submit or return
        $this->setVar(array(
            'HISTORY_USER_ID' => $owner,
            'HISTORY_USER_NAME' => $userHandler->formatName($usersList[$owner]),
            'HISTORY_USER_THUMB' => "/portrait?userId={$usersList[$owner]['synergy_id']}&size=square32"
        ));

        // submission by student
		if ($activity_item->isSubmission()) {

            // if you are a parent (and only a parent), you can only see plaintext, not a link
            if ($parentOnly) {
                $this->setVar('history_submitted_block', 'submitted work');
            }
            else {
                $this->parse('history_submitted_block', 'history_submitted');
            }

            // Remember most recent submission
            if (!isset($recent_submission)) {
	            $recent_submission = $activity_item;
            }

        }
        // return by teacher
		if ($activity_item->isReturn()) {

            // file return rather than mark
            if($history['HISTORY_RETURN_FILE_NAME']) {
                if($parentOnly) {
                    $this->setVar('history_returned_block', 'returned work');
                } else {
                    $this->parse('history_returned_block', 'history_returned');
                }
            }
            else {
                if ($history['HISTORY_COMMENT']) {
                    $this->parse('history_marked_hascomment_block', 'history_marked_hascomment');
                }
                $this->parse('history_marked_block', 'history_marked');
                $this->parse('work_grade_block', 'work_grade');
            }

            /* Remember the most recent returned work, because we need it
             * later on
             */
			if (!isset($recent_return)) {
				$recent_return = $activity_item;
			}
        }

		/* Alternating row color
		 */
		$this->setVar(array(
			'ROW_COLOR' => !($j%2) ? 'dark' : ''
		));

		$this->parse('work_block', 'work', true);

		$this->setVar('status_due_block', '');
		$this->setVar('status_awaiting_review_block', '');
		$this->setVar('status_graded_block', '');

		$j++;
	}

	$this->parse('subject_block', 'subject');
}
else {
    $this->setVar('work_block','');
    $this->parse('subject_block', 'subject');
}

if (isset($recent_submission)) {

   /* Show most recent submission
    * Hide Submit form
    * If multi-submit is enabled, show the Resubmit form
    * Find out whether the latest submission was overdue
    */

    // override the due gradient because we have submitted
    $this->setVar('BOX_DUEDATE_GRADIENT', 'black');

    if ($recent_submission->getDate()->getUnixTimestamp() > $submission->getDueDate()->getUnixTimestamp()) {
        $this->setVar('OVERDUE', 'overdue');
        $this->parse('overdue_block', 'overdue');
    }
    else {
        $this->setVar('OVERDUE','');
        $this->setVar('overdue_block', '');
    }

    $recentSubmissionDate = new Alaress_Date_Relative($recent_submission->getDate(), 'U');

    // get filetype details
    $ext   = pathinfo($recent_submission->getSubmission()->getFilename(), PATHINFO_EXTENSION);
    $mime  = $allowedHandler->getMime($ext);
    $ftype = $allowedHandler->getByField('mime', $mime);
	$size  = Alaress_Byte_Format::bytesToString($recent_submission->getSubmission()->getFilesize());

	// populate details into page
    $this->setVar(array(
        'MOST_RECENT_SUBMISSION_FILE_NAME'  => $recent_submission->getSubmission()->getFilename(),
        'MOST_RECENT_SUBMISSION_FILE_SIZE'  => $size,
        'MOST_RECENT_SUBMISSION_FILE_THUMB' => $ftype['iconFile'],
        'MOST_RECENT_SUBMISSION_FILE_DATE'  => $recentSubmissionDate->getRelativeTime(),
        'MOST_RECENT_SUBMISSION_FILE_ID'    => $recent_submission->getSubmission()->getId(),
    ));


	// if we are the owning student, display plagiarism details if they are available
	if($userID == $_SESSION['id'] && Config::get('plagscan_student_visible')) {

		// retrieve plagiarism score, if available
		$docMapper = Plagscan_DocumentMapper::getInstance();
		$plagDoc = $docMapper->findByColumn('file_id', $recent_submission->getSubmission()->getId())->current();
		// test: $plagDoc = $docMapper->findByColumn('file_id', 9)->current();

		// if we retrieved a plag document, use it and display to the student
		if($plagDoc->id && $plagDoc->status == Plagscan_Document::STATUS_COMPLETE) {

			// what colour should the plagiarism text be, and what should the tooltip say?
			if($plagDoc->score > 60) {
				$status = 'critical';
				$tooltip = 'Your submission is extremely similar to other documents scanned.';
			}
			elseif($plagDoc->score > 20) {
				$status = 'warn';
				$tooltip = 'Your submission is somewhat similar to other documents scanned.';
			}
			else {
				$status = 'ok';
				$tooltip = 'Your submission is quite different to other documents scanned.';
			}

			$this->setVar(array(
				'MOST_RECENT_SUBMISSION_FILE_PLAG_CLASS' => $status,
				'MOST_RECENT_SUBMISSION_FILE_PLAGSCORE' => $plagDoc->score,
				'MOST_RECENT_SUBMISSION_FILE_PLAG_TITLE' => $tooltip,
			));
			$this->parse('plag_score_block', 'plag_score');
		}
		else {
			$this->setVar('plag_score_block', '');
		}
	}
	else {
		$this->setVar('plag_score_block', '');
	}

	$submission->getOpenDate();
	$submission->getAcceptMultiple();
    // only allow a resubmission if the current user is the owner, if the submission box is accepting submissions, and if the current time is between open and late
    if(
        $userID == $_SESSION['id'] &&
        (mktime() > $submission->getOpenDate()->getUnixTimestamp()) &&
        (mktime() < $submission->getLateDate()->getUnixTimestamp()) &&
        $submission->getAcceptOnline() &&
        $submission->getAcceptMultiple()
    ) {
        $this->parse('action_resubmit_block', 'action_resubmit');
    } else {
        $this->setVar('action_resubmit_block', '');
    }

    // if parent, hide the "view submitted work" button
    if($parentOnly) {
        $this->setVar('recent_submission_block', '');
    } else {
        $this->parse('recent_submission_download_block', 'recent_submission_download');
        $this->parse('recent_submission_block', 'recent_submission');
    }

}
else {
    /* Hide most recent submission
     * Show Submit form
     * Hide the Resubmit form
     */
    if ($userID == $_SESSION['id']
        && mktime() > $submission->getOpenDate()->getUnixTimestamp()
        && mktime() < $submission->getLateDate()->getUnixTimestamp()
        && $submission->getAcceptOnline())
    {
        $this->parse('action_submit_block', 'action_submit');
    }
    else {
        $this->setVar('action_submit_block','');
    }

    $this->setVar('recent_submission_block', '');
    $this->setVar('action_resubmit_block', '');

}

if (!in_array($submission->getCategory()->getId(), $categoryIDList)) {
    $this->setVar(array(
        'action_submit_block' => '',
        'action_resubmit_block' => ''
    ));
}

/* Detect returned work
 */

if (isset($recent_return)) {

    // get filetype details
	$return = $recent_return->getReturnedWork();
    $ext   = pathinfo($return->getFilename(), PATHINFO_EXTENSION);
    $mime  = $allowedHandler->getMime($ext);
    $ftype = $allowedHandler->getByField('mime', $mime);
	$size  = Alaress_Byte_Format::bytesToString($return->getFilesize());

    $returnDate = new Alaress_Date_Relative($submission->getDueDate(),'y-m-d');

	$this->setVar(array(
		'RETURNED_WORK_COMMENT'    => Scrub::Text($return->getComment()),
		'RETURNED_WORK_MARK'       => Scrub::Text($return->getMark()),
		'RETURNED_WORK_FILE_ID'    => $return->getId(),
        'RETURNED_WORK_GRADIENT'   => $return->getGradientFromNormalizedMark(),
        'RETURNED_WORK_FILE_SIZE'  => $size,
        'RETURNED_WORK_FILE_NAME'  => Scrub::Text($return->getFilename()),
        'RETURNED_WORK_FILE_DATE'  => $returnDate->getLongDateOnly(),
        'RETURNED_WORK_FILE_THUMB' => $ftype['iconFile'],
	));

    if ($return->getFilename() && !$parentOnly) {
	    $this->parse('return_file_block', 'return_file');
    }
    else {
	    $this->setVar('return_file_block','');
    }

	$this->parse('returned_work_block', 'returned_work');
}
else {
	$this->setVar('returned_work_block', '');
}

// do fun rubric things
if($submission->getMarkType()->getId() == Component\SubmissionBox\MarkType::TYPE_RUBRIC) {
    $rb = new DueWork_Rubric_Lightbox($this);
    $rb->render('rubric_block', $submission->getId(), $user['uid']);
}
else {
    $this->setVar('rubric_block', '');
}


/* Only offer a publish button if there is a submission history OR a return history
 */
if (isset($recent_submission) || isset($recent_return)) {
	$this->parse('action_publish_block', 'action_publish');
}
else {
	$this->setVar('action_publish_block', '');
}