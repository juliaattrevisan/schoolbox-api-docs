<?php

require_once '../include/permission.php';
require_once '../../include/sitePage.php';
require_once '../../library/poll.php';

$pollHandler = new Poll;
$page = new SitePage;


if(is_numeric($_POST['pollID']) && is_numeric($_POST['categoryID']) && $_POST['vote']) {
	$pollHandler->placeVote($_POST['pollID'], $_POST['vote']);
	localRedir("/homepage/" . $_POST['categoryID']);
}
else if ($_POST['title'] && $_POST['categoryID']) {
	$poll = array('categoryID'	=> $_POST['categoryID'],
				  'title'		=> $_POST['title'],
				  'question'	=> $_POST['question'],
				  'option_0'	=> $_POST['option_0'],
				  'option_1'	=> $_POST['option_1'],
				  'option_2'	=> $_POST['option_2'],
				  'option_3'	=> $_POST['option_3'],
				  'option_4'	=> $_POST['option_4'],
				  'option_5'	=> $_POST['option_5'],
				  'option_6'	=> $_POST['option_6'],
				  'option_7'	=> $_POST['option_7'],
				  'option_8'	=> $_POST['option_8'],
				  'option_9'	=> $_POST['option_9'],
				  'frequency'	=> $_POST['frequency'],
				  'close'		=> $_POST['close'],
				  );
	if (is_numeric($_POST['pollID'])) {
		$poll['pollID'] = $_POST['pollID'];
		$pollHandler->update($poll);
	} else {
		$poll['pollID'] = $pollHandler->create($poll);
	}
	
	localRedir("/homepage/" . $_POST['categoryID']);
} 
else if ($_GET['delete']) {
	$poll = $pollHandler->getByID($_GET['delete']);
	$pollHandler->delete($_GET['delete']);
	localRedir("/homepage/" . $poll['categoryID']);
}
else if ($_GET['pollID']) {
	$page->setFile(TEMPLATE_content, "poll/modify.html");
	$page->setVar("TITLE", HEADING." - Modify Poll");
	
	$poll = $pollHandler->getByID($_GET['pollID']);
	
	$output = array('ACTION'	=> 'Modify',
					'POLL_ID'	=> $poll['pollID'],
					'CATEGORY_ID'	=> $poll['categoryID'],
					'POLL_TITLE'	=> $poll['title'],
					'QUESTION'	=> $poll['question'],
					'OPTION_0'	=> $poll['option_0'],
					'OPTION_1'	=> $poll['option_1'],
					'OPTION_2'	=> $poll['option_2'],
					'OPTION_3'	=> $poll['option_3'],
					'OPTION_4'	=> $poll['option_4'],
					'OPTION_5'	=> $poll['option_5'],
					'OPTION_6'	=> $poll['option_6'],
					'OPTION_7'	=> $poll['option_7'],
					'OPTION_8'	=> $poll['option_8'],
					'OPTION_9'	=> $poll['option_9'],
					'CLOSE'		=> $poll['close'],
					'FREQUENCY_ONCE_SELECTED'	=> '',
					'FREQUENCY_HOURLY_SELECTED'	=> '',
					'FREQUENCY_DAILY_SELECTED'	=> '',
					'FREQUENCY_' . strtoupper($poll['frequency']) . '_SELECTED'	=> 'selected="selected"',
					);
					
	$page->setVar($output);
	$page->outputPage();
	
}
else if ($_GET['categoryID']) {
	$page->setFile(TEMPLATE_content, "poll/modify.html");
	$page->setVar("TITLE", HEADING." - Add Poll");

	$output = array('ACTION'	=> 'Add',
					'POLL_ID'	=> '',
					'CATEGORY_ID'	=> $_GET['categoryID'],
					'POLL_TITLE'	=> '',
					'QUESTION'	=> '',
					'OPTION_0'	=> '',
					'OPTION_1'	=> '',
					'OPTION_2'	=> '',
					'OPTION_3'	=> '',
					'OPTION_4'	=> '',
					'OPTION_5'	=> '',
					'OPTION_6'	=> '',
					'OPTION_7'	=> '',
					'OPTION_8'	=> '',
					'OPTION_9'	=> '',
					'CLOSE'		=> '',
					'FREQUENCY_ONCE_SELECTED'	=> '',
					'FREQUENCY_HOURLY_SELECTED'	=> '',
					'FREQUENCY_DAILY_SELECTED'	=> '',					
					);
	$page->setVar($output);
	$page->outputPage();
}
else
	error(2);
?>