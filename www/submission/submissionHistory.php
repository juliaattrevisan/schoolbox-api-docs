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
$owner = $userHandler->getByID($submission->getOwner());

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

$allWork = $submissionHandler->getSubmissionHistory($userID, $this->arg[0]);

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
            'FILE_DATE'  => date('jS M Y, g:ia', strtotime($file->getUploadedOn()))
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

$usersList = array();

if (is_array($allWork) && count($allWork)) {
	$j = 0;

	/* Make submit button available/unavailable depending on submissions and
	 * returns from $allWork
	 */
	$this->setVar('action_submit_block', '');

    $dem = DueWork_ErrorMapper::getInstance();
    $errors = $dem->listDisplayValues();

	foreach($allWork as $currentWork) {

        // reset blocks
        foreach($historyActivityTypes as $historyType) {
            $this->setVar("history_{$historyType}_block", '');
        }
        $this->setVar(array(
            'history_marked_hascomment_block' => '',
            'history_returned_block' => '',
            'work_grade_block' => ''
        ));

        $workDate = new Alaress_Date_Relative($currentWork['date'], 'U');

        // if submission item
        if($currentWork['sid'] != null) {
            $historyClass = 'submit';
            $historyIcon = 'up-arrow';
        }
        // if return item
        elseif($currentWork['rid'] != null) {
            // work returned
            if($currentWork['filename']) {
                $historyClass = 'return';
                $historyIcon = 'submitted';
            }
            // mark given
            else {
                $historyClass = 'mark';
                $historyIcon = 'tick';
            }
        }
        // otherwise extension
        else {
            $historyClass = 'extend';
            $historyIcon = 'clock';
        }

        $comment = Scrub::Text($currentWork['comment']);

        // perform shortening if the marktype is letter, or if the given grade is an error
        if($submission->getMarkType()->getId() == Component\SubmissionBox\MarkType::TYPE_LETTER || in_array($currentWork['mark'], $errors)) {
            $mark = $returnHandler->shortenGrade($currentWork['mark']);
        } else {
            $mark = Scrub::Text($currentWork['mark']);
        }

        $gradient = max(0, min(10, (int)($currentWork['norm_mark'] / 10)));

		$this->setVar(array(
            'HISTORY_CLASS' => $historyClass,
            'HISTORY_ICON' => $historyIcon,
            'HISTORY_GRADIENT' => $gradient,
			'HISTORY_DATETIME' => $workDate->getRelativeTime(),
			'HISTORY_MARK' => $mark,
			'HISTORY_COMMENT' => $comment,
			'HISTORY_FILE_ID' => $currentWork['fid'],
			'HISTORY_SUBMISSION_FILE_ID' => $currentWork['sid'],
			'HISTORY_SUBMISSION_FILE_NAME' => $currentWork['sfname'],
			'HISTORY_RETURN_FILE_ID' => $currentWork['rid'],

            'HAS_GRADE' => $mark || $mark === 0 ? 'grade' : '',
		));

         // if the user in question has not previously been seen, retrieve
        if(!isset($usersList[$currentWork['owner']])) {
            $usersList[$currentWork['owner']] = $userHandler->getByID($currentWork['owner']);
        }

        // [owner] is contextually set to either the student or teacher, depending on whether it's submit or return
        $this->setVar(array(
            'HISTORY_USER_ID' => $currentWork['owner'],
            'HISTORY_USER_NAME' => $userHandler->formatName($usersList[$currentWork['owner']]),
            'HISTORY_USER_THUMB' => "/portrait?userId={$usersList[$currentWork['owner']]['synergy_id']}&size=square32"
        ));

        // submission by student
        if ($currentWork['sid'] != null) {

            // if you are a parent (and only a parent), you can only see plaintext, not a link
            if($parentOnly) {
                $this->setVar('history_submitted_block', 'submitted work');
            } else {
                $this->parse('history_submitted_block', 'history_submitted');
            }

            // Remember most recent submission
            if(!is_numeric($recentSubmission['sid'])) {
                $recentSubmission = $currentWork;
            }

        }
        // return by teacher
		else if ($currentWork['rid'] != null) {

            $this->setVar('HISTORY_RETURN_FILE_NAME', Scrub::text($currentWork['filename']));

            // file return rather than mark
            if($currentWork['filename']) {
                if($parentOnly) {
                    $this->setVar('history_returned_block', 'returned work');
                } else {
                    $this->parse('history_returned_block', 'history_returned');
                }
            }
            else {
                if($comment) {
                    $this->parse('history_marked_hascomment_block', 'history_marked_hascomment');
                }
                $this->parse('history_marked_block', 'history_marked');
                $this->parse('work_grade_block', 'work_grade');
            }

            /* Remember the most recent returned work, because we need it
             * later on
             */
            if (!is_numeric($returnedWork['rid'])) {
                $returnedWork = $currentWork;
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

// set the teacher details
$teacher = $userHandler->getByID($submission->getOwner());
$this->setVar(array(
    'TEACHER_ID' => $teacher['uid'],
    'TEACHER_NAME' => $userHandler->formatName($teacher),
    'TEACHER_THUMB' => "/portrait?userId={$teacher['synergy_id']}&size=square32",
));

if($recentSubmission['sid']) {

   /* Show most recent submission
    * Hide Submit form
    * If multi-submit is enabled, show the Resubmit form
    * Find out whether the latest submission was overdue
    */

    // override the due gradient because we have submitted
    $this->setVar('BOX_DUEDATE_GRADIENT', 'black');

    if ($recentSubmission['date'] > $submission->getDueDate()->getUnixTimestamp()) {
        $this->setVar('OVERDUE', 'overdue');
        $this->parse('overdue_block', 'overdue');
    }
    else {
        $this->setVar('OVERDUE','');
        $this->setVar('overdue_block', '');
    }

    $recentSubmissionDate = new Alaress_Date_Relative($recentSubmission['date'], 'U');

    // get filetype details
    $ext = substr($recentSubmission['sfname'], strrpos($recentSubmission['sfname'], '.') + 1);
    $mime = $allowedHandler->getMime($ext);
    $ftype = $allowedHandler->getByField('mime', $mime);

    // get filesize (isn't cached on db table, unfortunately)
    $filename = Cfg::get('submission_dir') . '/' . $recentSubmission['sid'] . '.dat';
    if(file_exists($filename)) {
        $size = Alaress_Byte_Format::bytesToString(filesize($filename));
    } else {
        $size = '0 KB';
    }

	// populate details into page
    $this->setVar(array(
        'MOST_RECENT_SUBMISSION_FILE_NAME' => $recentSubmission['sfname'],
        'MOST_RECENT_SUBMISSION_FILE_SIZE' => $size,
        'MOST_RECENT_SUBMISSION_FILE_THUMB' => $ftype['iconFile'],
        'MOST_RECENT_SUBMISSION_FILE_DATE' => $recentSubmissionDate->getRelativeTime(),
        'MOST_RECENT_SUBMISSION_FILE_ID' => $recentSubmission['sid'],
    ));


	// if we are the owning student, display plagiarism details if they are available
	if($userID == $_SESSION['id'] && Config::get('plagscan_student_visible')) {

		// retrieve plagiarism score, if available
		$docMapper = Plagscan_DocumentMapper::getInstance();
		$plagDoc = $docMapper->findByColumn('file_id', $recentSubmission['sid'])->current();
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
    if($userID == $_SESSION['id'] && mktime() > strtotime($submission->getOpenDate()) && mktime() < strtotime($submission->getLateDate()) && $submission->getAcceptOnline())
	    $this->parse('action_submit_block', 'action_submit');
    else
        $this->setVar('action_submit_block','');

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

if (is_numeric($returnedWork['rid'])) {

    // get filetype details
    $ext = substr($returnedWork['filename'], strrpos($returnedWork['filename'], '.') + 1);
    $mime = $allowedHandler->getMime($ext);
    $ftype = $allowedHandler->getByField('mime', $mime);

    $filename = Cfg::get('returns_dir') . '/' . $returnedWork['rid'] . '.dat';
    if(file_exists($filename)) {
        $size = Alaress_Byte_Format::bytesToString(filesize($filename));
    } else {
        $size = '0 KB';
    }

    $returnDate = new Alaress_Date_Relative($submission->getDueDate(),'y-m-d');

	$this->setVar(array(
		'RETURNED_WORK_COMMENT' => Scrub::Text($returnedWork['comment']),
		'RETURNED_WORK_MARK' => Scrub::Text($returnedWork['mark']),
		'RETURNED_WORK_FILE_ID' => $returnedWork['rid'],
        'RETURNED_WORK_GRADIENT' => max(0, min(10, (int)($returnedWork['norm_mark'] / 10))),
        'RETURNED_WORK_FILE_SIZE' => $size,
        'RETURNED_WORK_FILE_NAME' => Scrub::Text($returnedWork['filename']),
        'RETURNED_WORK_FILE_DATE' => $returnDate->getLongDateOnly(),
        'RETURNED_WORK_FILE_THUMB'=> $ftype['iconFile'],
	));

    if($returnedWork['filename'] && !$parentOnly)
        $this->parse('return_file_block', 'return_file');
    else
        $this->setVar('return_file_block','');

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

?>
