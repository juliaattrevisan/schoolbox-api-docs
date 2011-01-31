<?php

require_once SITE_root . '/library/synergetic-user.php';
require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/synergetic-class.php';

$this->setFile(TEMPLATE_content, 'submission/submissionHistory.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'subject', 'subject_block');
$this->setBlock(TEMPLATE_content, 'download', 'download_block');
$this->setBlock(TEMPLATE_content, 'action_submit', 'action_submit_block');
$this->setBlock('subject', 'work', 'work_block');
$this->setBlock('work', 'action_view_submitted', 'action_view_submitted_block');
$this->setBlock('work', 'action_view_returned', 'action_view_returned_block');

$userHandler = new User;
$groupHandler = new Group;
$submissionHandler = new Submission;
$returnHandler = new SubmissionReturn;

/* ideally session children would use the id as the array key as this is ridiculous */
$childList = array();

if (is_array($_SESSION['children'])) {
	foreach ($_SESSION['children'] as $child) {
		$childList[] = $child['id'];
	}
}

// get selected user
if (is_array($_SESSION['children'])) {
    $userID = $_SESSION['children'][0]['id'];
}
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

if (is_numeric($this->arg[1]) && in_array($this->arg[1], $childList)) {
	$userID = $this->arg[1];
}
else if (is_numeric($this->arg[1]) && $groupHandler->isStaff()) {
	$userID = $this->arg[1];
}

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}

if (is_array($_SESSION['children'])) {
	$this->dropDown('child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME'));

	$this->parse('is_parent_block', 'is_parent');
}

// get category list
$subjectList = false;

if (SYNERGY_enable && $user['synergy_id'])  {
	$synUser = new SynergeticUser;
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

$this->setVar(array(
	'WORK_CATEGORYNAME' => $submissionBox['categoryName'],
	'WORK_DESCRIPTION' => $submissionBox['description']
));

if (is_numeric($submissionBox['fid'])) {
	$this->setVar(array(
		'WORK_FILE_ID' => $submissionBox['fid']
	));

	$this->parse('download_block', 'download');
}
else {
	$this->setVar('download_block', '');
}

$allWork = $submissionHandler->getSubmissionHistory($this->arg[0]);

if (is_array($allWork) && count($allWork)) {
	$j = 0;

	/* Make submit button available/unavailable depending on submissions and
	 * returns from $allWork
	 */
	$this->setVar('action_submit_block', '');

	foreach($allWork as $currentWork) {
		$this->setVar(array(
			'WORK_DATE' => outputDate(DATE_long_format, $currentWork['date']),
			'WORK_MARK' => $currentWork['mark'],
			'WORK_COMMENT' => $currentWork['comment'],
			'WORK_BOX_ID' => $currentWork['bid'],
			'WORK_FILE_ID' => $currentWork['fid'],
			'WORK_SUBMIT_FILE_ID' => $currentWork['sid'],
			'WORK_RETURN_FILE_ID' => $currentWork['rid'],
		));

		if ($currentWork['sid'] != null) {
			$this->setVar('action_submit_block', '');
			
			if ($currentWork['rid'] != null) {
				$this->setVar('action_view_submitted_block', '');
				$this->parse('action_view_returned_block', 'action_view_returned');
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
}
else {
	$this->setVar(array(
		'WORK_BOX_ID' => $submissionBox['id']
	));

	$this->parse('action_submit_block', 'action_submit');
	$this->setVar('subject_block', '');
}

?>