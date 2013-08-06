<?php

$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);
$userHandler = new User();

// check permission
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
	error(23);
}

// if we are editing an existing news article
if (is_numeric($this->arg[0])) {
	$this->setFile(TEMPLATE_content, 'news/modify.html');
    $this->setBlock(TEMPLATE_content, 'image', 'image_block');
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
        'NEWS_IMAGE' => $news['item_image'],
		'BODY' => $news['item_body'],
		'ITEM_DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
		'ITEM_DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
	);

	$this->setVar($output);

    if($news['item_image']) {
        $this->parse('image_block','image');
    }
    else {
        $this->setVar('image_block','');
    }

	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$this->checkList('group_selection', $groupList, $news['groupList']);
	$this->checkList('campus_selection', $campusList, $news['campusList'], 'campus_row');

	$userList = $newsHandler->getUserList($news['item_id']);

	if (is_array($userList)) {
        // set the advanced options to be shown by default since there is information in there
        $this->setVar(array('SHOW_ADVANCED' => ' style="display: table-row;"',
                            'OPAC_ADVANCED' => ' style="opacity: 1;"',
                            'ADV_OFF'       => ' style="display:none"',
                            'ADV_ON'        => ''));

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
        // no data in advanced options, hide them by default
        $this->setVar(array('SHOW_ADVANCED' => '',
                            'OPAC_ADVANCED' => '',
                            'ADV_OFF'       => '',
                            'ADV_ON'        => ' style="display:none"'));
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

// if we are creating a new news article
else {
	$this->setFile(TEMPLATE_content, 'news/modify.html');
	$this->setBlock(TEMPLATE_content, 'campus_row', 'campus_row_block');
    $this->setBlock(TEMPLATE_content, 'image', 'image_block');
	$this->setBlock(TEMPLATE_content, 'current_users', 'current_users_block');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	$this->setTitle('Add News');
	$this->setTab('news');


    $pubTime = time();
    $expTime = strtotime(Cfg::get('news_default_expiry'));

    $publishedDate = new Alaress_Date_Format($pubTime, 'U');
	$expiryDate = new Alaress_Date_Format($expTime >= $pubTime ? $expTime : $pubTime, 'U');

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

    $this->setVar('image_block','');
    
	$this->setVar('delete_button_block', '');


    // set the advanced options to be hidden by default since there can't be any data in there
    $this->setVar(array('SHOW_ADVANCED' => '',
                        'OPAC_ADVANCED' => '',
                        'ADV_OFF'       => '',
                        'ADV_ON'        => ' style="display:none"'));
}
