<?php

$newsHandler = new News();
$groupHandler = new Group(NEWS_SERVICE);
$userHandler = new User();

// check permission
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS) {
	error(23);
}

$this->setFile(TEMPLATE_content, 'news/modify.html');
$this->setBlock(TEMPLATE_content, 'campus_row', 'campus_row_block');
$this->setBlock(TEMPLATE_content, 'current_users', 'current_users_block');
$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
$this->setTab('news');

$zone = new \Schoolbox\Resource\Attachment\Zone('news-feature');
$zone->setThumbnailSize(64);
$zone->setAttachPrompt('Attach feature image');
$zone->setAttachmentsLabel('');
$zone->setMimeFilter('image/*');
$zone->setModalTitle('Select feature image');

// if we are editing an existing news article
if (is_numeric($this->arg[0])) {
	$this->setTitle('Modify News');

	$news = $newsHandler->getByID($this->arg[0]);

	if (!isset($news['item_id'])) {
		error(101);
	}

	// check user is news owner
	if ($news['item_owner'] != $_SESSION['id'] && $permission < FULL_ACCESS) {
		error(23);
	}

	$publishedDate = new Alaress_Date_Format($news['item_datetime_published'], Alaress_Date_Format::DATABASE_FORMAT);
	$expiryDate = new Alaress_Date_Format($news['item_datetime_expires'], Alaress_Date_Format::DATABASE_FORMAT);

	$output = array(
		'ACTION'                  => 'Modify',
		'NEWS_ID'                 => $news['item_id'],
		'HEADING'                 => Scrub::text($news['item_heading']),
        'NEWS_IMAGE'              => $news['item_image'],
		'BODY'                    => Scrub::text($news['item_body']),
		'ITEM_DATETIME_PUBLISHED' => $publishedDate->getLocalShort(),
        'PUBLISH_HOUR'            => $publishedDate->isAtStartOfDay() ? '' : $publishedDate->get12Hour(),
        'PUBLISH_MIN'             => $publishedDate->isAtStartOfDay() ? '' : $publishedDate->getMinute(true),
        'PUBLISH_PM'              => !$publishedDate->isAtStartOfDay() && $publishedDate->isPM() ? 'selected="selected"' : '',
		'ITEM_DATETIME_EXPIRES'   => $expiryDate->getLocalShort(),
        'EXPIRES_HOUR'            => $expiryDate->isAtEndOfDay() ? '' : $expiryDate->get12Hour(),
        'EXPIRES_MIN'             => $expiryDate->isAtEndOfDay() ? '' : $expiryDate->getMinute(true),
        'EXPIRES_PM'              => !$expiryDate->isAtEndOfDay() && $expiryDate->isPM() ? 'selected="selected"' : '',
        'SHOW_ADVANCED'           => '',
        'OPAC_ADVANCED'           => '',
        'ADV_OFF'                 => '',
        'ADV_ON'                  => ' style="display:none"',
        'USER_LIST'               => '[]',
	);

	$this->setVar($output);

    $zone->attachFiles(array($news['item_image']));
    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

	$groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$this->checkList('group_selection', $groupList, $news['groupList']);
	$this->checkList('campus_selection', $campusList, $news['campusList'], 'campus_row');

	$userList = $newsHandler->getUserList($news['item_id']);
	if (is_array($userList) || !$expiryDate->isAtEndOfDay()) {
        // set the advanced options to be shown by default since there is information in there
        $this->setVar(
            array(
                'SHOW_ADVANCED' => ' style="display: table-row;"',
                'OPAC_ADVANCED' => ' style="opacity: 1;"',
                'ADV_OFF'       => ' style="display:none"',
                'ADV_ON'        => '',
            )
        );
    }
    if (is_array($userList)) {
        $userOut = array();
		foreach ($userList as $user) {
            $userOut[] = array(
                'id'   => intval($user['id']),
                'name' => $userHandler->formatName($user),
            );
		}
        $this->setVar('USER_LIST', json_encode($userOut));
	}

	$this->dropDown('topic_list', $newsHandler->getTopicList(), $news['item_topic_id'], TEMPLATE_content, array(
		'newsTopicID' => 'TOPIC_ID',
		'name' => 'TOPIC_NAME'
	));

	$this->parse('campus_row_block', 'campus_row');

	$this->parse("delete_button_block", "delete_button");
}

// if we are creating a new news article
else {
	$this->setTitle('Add News');

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
        'PUBLISH_HOUR'          => '',
        'PUBLISH_MIN'           => '',
        'PUBLISH_PM'            => '',
		'ITEM_DATETIME_EXPIRES' => $expiryDate->getLocalShort(),
        'EXPIRES_HOUR'          => '',
        'EXPIRES_MIN'           => '',
        'EXPIRES_PM'            =>'',
	);

	$this->setVar($output);

    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');

    $groupList = $newsHandler->getGroupList();
	$campusList = $newsHandler->getCampusList();

	$this->checkList('group_selection', $groupList);
	$this->checkList('campus_selection', $campusList, array(), 'campus_row');

	$this->dropDown('topic_list', $newsHandler->getTopicList(), '', TEMPLATE_content, array(
		'newsTopicID' => 'TOPIC_ID',
		'name' => 'TOPIC_NAME'
	));

	$this->parse('campus_row_block', 'campus_row');

	$this->setVar('delete_button_block', '');


    // set the advanced options to be hidden by default since there can't be any data in there
    $this->setVar(array(
        'SHOW_ADVANCED' => '',
        'OPAC_ADVANCED' => '',
        'ADV_OFF'       => '',
        'ADV_ON'        => ' style="display:none"',
        'USER_LIST'     => '[]',
    ));
}
