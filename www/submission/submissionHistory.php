<?php

require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$this->setFile(TEMPLATE_content, 'submission/submissionHistory.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock(TEMPLATE_content, 'download', 'download_block');
$this->setBlock(TEMPLATE_content, 'action_submit', 'action_submit_block');
$this->setBlock(TEMPLATE_content, 'recent_submission', 'recent_submission_block');
$this->setBlock(TEMPLATE_content, 'returned_work', 'returned_work_block');
$this->setBlock('recent_submission', 'overdue', 'overdue_block');
$this->setBlock('subject', 'work', 'work_block');
$this->setBlock('work', 'action_view_submitted', 'action_view_submitted_block');
$this->setBlock('work', 'action_view_returned', 'action_view_returned_block');

$userHandler = new User;
$groupHandler = new Group;
$submissionHandler = new Submission;
$returnHandler = new SubmissionReturn;


if (is_numeric($this->arg[1]) && ($groupHandler->isParentOfChile($this->arg[1]) || $groupHandler->isStaff() || $_SESSION['id'] == $this->arg[1])) {
	$userID = $this->arg[1];
}
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}


$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}

// get category list
$subjectList = false;

if (Cfg::get('external_enable') && $user['synergy_id'])  {
        require_once SITE_root . '/library/'.Cfg::get('external_type').'/ExternalUser.php';
	$synUser = new ExternalUser;
	$subjectList = $synUser->getSubjectList($user['synergy_id'], $user['uid']);
}

$categoryList = $groupHandler->involvedCategoryList($user['uid'], $subjectList, 1);

if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

// Catch if not involved in any categories
if (count($categoryList) == 0) {
	error(135);
}

/* Submission Box Details
 */
$submissionBox = $submissionHandler->getByID($this->arg[0]);
$owner = $userHandler->getByID($submissionBox['owner']);

/* If you aren't in the category, deny access
 */

if (!in_array($submissionBox['category'], $categoryIDList)) {
	error(17);
}

$this->setVar(array(
	'BOX_CATEGORYNAME' => $submissionBox['categoryName'],
    'BOX_CATEGORYID'    => $submissionBox['cid'],
	'BOX_DESCRIPTION' => $submissionBox['description'],
	'BOX_OWNER' => '<a href="'.$_SESSION['mailhandle'].formatEmail($owner).'">'.formatName($owner).'</a>',
	'BOX_DUEDATE' => date(Cfg::get('date_short'), strtotime($submissionBox['date'])),
));

/* Check whether a file is associated with the work
 */
if ($submissionBox['fid'] && is_numeric($submissionBox['fid'])) {
	$this->setVar(array(
		'WORK_FILE_ID' => $submissionBox['fid']
	));

	$this->parse('download_block', 'download');
}
else {
	$this->setVar('download_block', '');
}

$allWork = $submissionHandler->getSubmissionHistory($userID, $this->arg[0]);

if (is_array($allWork) && count($allWork)) {
	$j = 0;

	/* Make submit button available/unavailable depending on submissions and
	 * returns from $allWork
	 */
	$this->setVar('action_submit_block', '');

	foreach($allWork as $currentWork) {
		$this->setVar(array(
			'WORK_DATE' => outputDate(Cfg::get('date_long'), $currentWork['date']),
			'WORK_MARK' => $currentWork['mark'],
			'WORK_COMMENT' => $currentWork['comment'],
			'WORK_BOX_ID' => $currentWork['bid'],
			'WORK_FILE_ID' => $currentWork['fid'],
			'WORK_SUBMIT_FILE_ID' => $currentWork['sid'],
			'WORK_RETURN_FILE_ID' => $currentWork['rid'],
		));

		if ($currentWork['sid'] != null) {
			$this->setVar('action_submit_block', '');
			
			/* Remember most recent submission
			 */
			$recentSubmission['sid'] = $currentWork['sid'];
			$recentSubmission['date'] = $currentWork['date'];

			if ($currentWork['rid'] != null) {
				$this->setVar('action_view_submitted_block', '');
				$this->parse('action_view_returned_block', 'action_view_returned');

				/* Remember the most recent returned work, because we need it
				 * later on
				 */
				if (!is_numeric($rid)) {
					$returnedWork['fileID'] = $currentWork['rid'];
					$returnedWork['comment'] = $currentWork['comment'];
					$returnedWork['mark'] = $currentWork['mark'];
				}
			}
			else {
				$this->parse('action_view_submitted_block', 'action_view_submitted');
				$this->setVar('action_view_returned_block', '');
			}
		}
		else {
			$this->parse('action_submit_block', 'action_submit');
			$this->setVar('action_view_submitted_block', '');
			$this->setVar('action_view_returned_block', '');
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

	/* Show most recent submisison
	 * Hide Submit form
	 * Find out whether the latest submission was overdue
	 */

	if ($recentSubmission['date'] > date('U', strtotime($submissionBox['date']))) {
		$this->setVar(array(
			'OVERDUE' => 'overdue',
		));

		$this->parse('overdue_block', 'overdue');
	}
	else {
		$this->setVar(array(
			'OVERDUE' => '',
		));

		$this->setVar('overdue_block', '');
	}
	
	$this->setVar(array(
		'MOST_RECENT_SUBMISSION_DATE' => outputDate(Cfg::get('date_long'), $recentSubmission['date']),
		'MOST_RECENT_SUBMISSION_FILE_ID' => $recentSubmission['sid'],
	));

	$this->parse('recent_submission_block', 'recent_submission');
}
else {

	/* Hide most recent submission
	 * Show Submit form
	 */
	$this->setVar(array(
		'WORK_BOX_ID' => $submissionBox['id']
	));

    if(mktime() > strtotime($submissionBox['open_date']) && mktime() < strtotime($submissionBox['late_date']) && $submissionBox['accept_submission'])
	    $this->parse('action_submit_block', 'action_submit');
    else
        $this->setVar('action_submit_block','');

	$this->setVar('recent_submission_block', '');

	$this->setVar('subject_block', '');
}

/* Detect returned work
 */
if (is_numeric($returnedWork['fileID'])) {
	$this->setVar(array(
		'RETURNED_WORK_COMMENT' => $returnedWork['comment'],
		'RETURNED_WORK_MARK' => $returnedWork['mark'],
		'RETURNED_WORK_FILE_ID' => $returnedWork['fileID'],
	));

	$this->parse('returned_work_block', 'returned_work');
}
else {
	$this->setVar('returned_work_block', '');
}


?>