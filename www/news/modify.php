<?php

require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/sitePage.php';

require_once $_SERVER['DOCUMENT_ROOT'] .'/../library/news.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/permission.php';

$page = new SitePage(); 
$newsHandler = new News;

$permLevel = perm(0,3);

if ($_POST['heading'] && $permLevel >= 2) {
	$news = array('item_heading' => $_POST['heading'],
				  'item_body'		 => $_POST['body'],
				  'groupList'	=> $_POST['group'],
				  'campusList'	=> $_POST['campus'],
				  );
	
	if ((int)$_POST['expires'] > 0)
		$news['item_datetime_expires'] = outputDate(DATE_database, strtotime("+" . $_POST['expires'] . " hours"));

	if (is_numeric($_POST['newsID'])) {
		$news['item_id'] = $_POST['newsID'];
		$newsHandler->update($news);
	} else
		$newsHandler->create($news);

	localRedir("/news/");
} 
else if (is_numeric($_GET['delete']) && $permLevel >= 2) {
	$newsHandler->delete($_GET['delete']);
	localRedir("/news/");
}
else if (is_numeric($_GET['newsID']) && $permLevel >= 2) {
	$page->setFile(TEMPLATE_content, "news/modify.html");
	$page->setBlock(TEMPLATE_content, "campus_row", "campus_row_block");
	$page->setBlock(TEMPLATE_content, "delete_button", "delete_button_block");
	$page->setVar("TITLE", HEADING." - Modify News");
	$page->setTab("news");
	
	$news = $newsHandler->getByID($_GET['newsID']);
	
	$output = array('ACTION'	=> 'Modify',
					'NEWS_ID'	=> $news['item_id'],
					'HEADING'	=> $news['item_heading'],
					'BODY'		=> $news['item_body'],
					'LEAVE_OPTION'	=> '<option>Keep Existing</option>',
					'DEFAULT_TIME_SELECTED' => '',
					);
					
	$page->setVar($output);
	
	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();
	
	$page->dropDown("group_selection", $groupList, $news['groupList']);
	$page->dropDown("campus_selection", $campusList, $news['campusList'], "campus_row");
	
	if (count($campusList) > 1)
		$page->parse("campus_row_block", "campus_row");
	else
		$page->setVar("campus_row_block", "");
		
	if ($permLevel >= 3)
		$page->parse("delete_button_block", "delete_button");
	else
		$page->setVar("delete_button_block", "");

	$page->outputPage();
}
else if ($permLevel >= 2) {
	$page->setFile(TEMPLATE_content, "news/modify.html");
	$page->setBlock(TEMPLATE_content, "campus_row", "campus_row_block");
	$page->setBlock(TEMPLATE_content, "delete_button", "delete_button_block");
	$page->setVar("TITLE", HEADING." - Add News");
	$page->setTab("news");

	$output = array('ACTION'	=> 'Add',
					'NEWS_ID'	=> '',
					'HEADING'	=> '',
					'BODY'		=> '',
					'LEAVE_OPTION'	=> '',
					'DEFAULT_TIME_SELECTED' => 'selected="selected"',
					);

	$page->setVar($output);


	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$page->dropDown("group_selection", $groupList);
	$page->dropDown("campus_selection", $campusList, array(), "campus_row");

	if (count($campusList) > 1)
		$page->parse("campus_row_block", "campus_row");
	else
		$page->setVar("campus_row_block", "");
	
	$page->setVar("delete_button_block", "");

	$page->outputPage();
	
} else
	error(23);
?>