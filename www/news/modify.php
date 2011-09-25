<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/news.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);
$userHandler = new User();

// check permission
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
	error(23);
}

if (is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'news/modify.html');
	$this->setBlock(TEMPLATE_content, 'campus_row', 'campus_row_block');
	$this->setBlock(TEMPLATE_content, 'current_users', 'current_users_block');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	$this->setTitle('Modify News');
	$this->setTab('news');

	$news = $newsHandler->getByID($this->arg[0]);

	if (!isset($news['item_id'])) {
		error(101);
	}

	// check user is news owner
	if ($news['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
		error(23);
	}

	$publishedDate = new Alaress_Date_Format($news['item_datetime_published'], 'y-m-d');
	$expiryDate = new Alaress_Date_Format($news['item_datetime_expires'], 'y-m-d');

	$output = array(
		'ACTION' => 'Modify',
		'NEWS_ID' => $news['item_id'],
		'HEADING' => $news['item_heading'],
		'BODY' => $news['item_body'],
		'ITEM_DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
		'ITEM_DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
	);

	$this->setVar($output);

	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$this->checkList('group_selection', $groupList, $news['groupList']);
	$this->checkList('campus_selection', $campusList, $news['campusList'], 'campus_row');

	$userList = $newsHandler->getUserList($news['item_id']);

	if (is_array($userList)) {
		foreach ($userList as $user) {
			$userOut = array(
				'USER_ID' => $user['id'],
				'USER_NAME' => $userHandler->formatName($user),
			);

			$this->setVar($userOut);
			$this->parse('current_users_block', 'current_users', true);
		}
	}
	else
	{
		$this->setVar('current_users_block', '');
	}

	$this->dropDown('topic_list', $newsHandler->getTopicList(), $news['item_topic_id'], TEMPLATE_content, array(
		'newsTopicID' => 'TOPIC_ID',
		'name' => 'TOPIC_NAME'
	));

	if (count($campusList) > 1) {
		$this->parse('campus_row_block', 'campus_row');
	}
	else
	{
		$this->setVar('campus_row_block', '');
	}

	$this->parse("delete_button_block", "delete_button");
}
else {
	$this->setFile(TEMPLATE_content, 'news/modify.html');
	$this->setBlock(TEMPLATE_content, 'campus_row', 'campus_row_block');
	$this->setBlock(TEMPLATE_content, 'current_users', 'current_users_block');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	$this->setTitle('Add News');
	$this->setTab('news');

	$publishedDate = new Alaress_Date_Format(time(), 'U');
	$expiryDate = new Alaress_Date_Format(strtotime('+7 days'), 'U');

	$output = array(
		'ACTION' => 'Add',
		'NEWS_ID' => '',
		'HEADING' => '',
		'BODY' => '',
        'current_users_block' => '',
		'ITEM_DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
		'ITEM_DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
	);

	$this->setVar($output);

	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$this->checkList('group_selection', $groupList);
	$this->checkList('campus_selection', $campusList, array(), 'campus_row');

	$this->dropDown('topic_list', $newsHandler->getTopicList(), '', TEMPLATE_content, array(
		'newsTopicID' => 'TOPIC_ID',
		'name' => 'TOPIC_NAME'
	));

	if (count($campusList) > 1) {
		$this->parse('campus_row_block', 'campus_row');
	}
	else {
		$this->setVar('campus_row_block', '');
	}

	$this->setVar('delete_button_block', '');
}
