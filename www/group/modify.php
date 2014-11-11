<?php


use Schoolbox\Config\ConfigMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;

$this->setFile(TEMPLATE_content, 'group/modify.html');
$this->setBlock(TEMPLATE_content, 'groupOwner', 'groupOwner_block');
$this->setBlock(TEMPLATE_content, 'groupUnmanaged', 'groupUnmanaged_block');
$this->setBlock(TEMPLATE_content, 'member', 'member_block');
$this->setBlock('member', 'make_owner', 'make_owner_block');
$this->setBlock('member', 'msg_enabled_userfollow', 'msg_enabled_userfollow_block');

$this->setBlock(TEMPLATE_content, 'msg_enabled', 'msg_enabled_block');
$this->setBlock('msg_enabled', 'default_follow', 'default_follow_block');

$this->setBlock(TEMPLATE_content, 'msg_enabled_followth', 'msg_enabled_followth_block');

$this->setBlock(TEMPLATE_content, 'mail_all', 'mail_all_block');

$this->setBlock(TEMPLATE_content, "authorisation", "authorisation_block");
$this->setBlock("authorisation", "waiting", "waiting_block");

$this->setTitle('Modify User Group');
$this->setTab('resources');

$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryHandler = new Category;

if (is_numeric($this->arg[0])) {
    $messagingEnabled = ConfigMapper::getInstance()->get('messaging_enabled');

	$userHandler = new User();

	$category = $categoryHandler->getByID($this->arg[0]);

	// check access
	if($groupHandler->getPermission($this->arg[0]) < FULL_ACCESS)
		error(19);

	$this->setVar(
		array(
			'groupOwner_block'           => '',
			'groupUnmanaged_block'       => '',
            'msg_enabled_followth_block' => '',
            'msg_enabled_block'          => ''
		)
	);

	$output = array('CATEGORY_NAME'		=> Scrub::text($category['name']),
					'CATEGORY_ID'		=> $category['id'],
					'GROUP_TYPE_1'		=> '',
					'GROUP_TYPE_2'		=> '',
					'GROUP_TYPE_3'		=> '',
					'GROUP_TYPE_'.$category['type']	=> 'checked="checked"',
					);

	$this->setVar($output);

    $this->setVar('default_follow_block', '');

    $search = array(
        'user_id'        => 0,
        'msg_route_key'  => "service.1.{$category['id']}",
        'outgoing_level' => 0
    );

    $groupMessagesEnabled = !RerouteOverrideMapper::getInstance()->isRouteMuted(
        "service.1.{$category['id']}"
    );

    $output = array(
        'MSG_ENABLED_CHECKED'      => '',
        'MSG_FORCE_FOLLOW_CHECKED' => ''
    );

    if ($groupMessagesEnabled) {
        $output['MSG_ENABLED_CHECKED'] = 'checked="checked"';

        if ($category['force_follow']) {
            $output['MSG_FORCE_FOLLOW_CHECKED'] = 'checked="checked"';
        }
    }

    $this->setVar($output);


	if ($category['owner']) {
		$this->setVar(
			array(
				'GROUP_OWNER' => $userHandler->formatName($category),
				'OWNER_ID'    => $category['owner'],
			)
		);
		$this->parse('groupOwner_block', 'groupOwner');
	}
	else {
		$this->parse('groupUnmanaged_block', 'groupUnmanaged');
	}

    if ($messagingEnabled) {
        $this->parse('msg_enabled_block', 'msg_enabled');
        $this->parse('msg_enabled_followth_block', 'msg_enabled_followth');
    }

	$memberList   = $groupHandler->getMemberList($category['id']);
	//
	// Keep list of everyone to email
	$all_emails   = array();
	$email_object = new Email();
	if (is_array($memberList)) {
		foreach ($memberList as $member) {
			//
			// Don't email yourself
			if ($member['uid'] != $_SESSION['id']) {
				$all_emails[] = $email_object->formatEmailFromArray($member);
			}

            $search = array(
                'user_id'        => array(0, $member['uid']),
                'msg_route_key'  => "service.1.{$category['id']}",
                'outgoing_level' => 0
            );

            $following = !RerouteOverrideMapper::getInstance()->isRouteMuted(
                "service.1.{$category['id']}",
                $member['uid']
            );

			$output = array('USER_NAME'		=> $userHandler->formatName($member),
							'USER_JAVA'		=> addslashes($userHandler->formatName($member)),
							'USER_ID'		=> $member['uid'],
							'GROUP_CHECKED'	=> $member['type'] > 1 ? 'checked=\'checked\'' : '',
                            'USER_FOLLOWING' => $following ? 'Yes' : 'No'
							);

			$this->setVar($output);


			$this->setVar(array(
                'make_owner_block'             => '',
                'msg_enabled_userfollow_block' => ''
            ));

			if ($groupHandler->allowOwner($member['uid']))
				$this->parse('make_owner_block', 'make_owner');

            if ($messagingEnabled) {
                $this->parse('msg_enabled_userfollow_block', 'msg_enabled_userfollow');
            }

			$this->parse('member_block', 'member', true);
		}

	}
	else {
		$this->setVar('member_block', '');
	}

	//
	// Set up the email all block (PHPLib sucks)
	if (!empty($all_emails) && \Cfg::get('mail_enable')) {
		$this->setVar(array(
			'REPLYTO'  => $email_object->formatEmailFromArray($_SESSION),
			'EMAILALL' => implode(',', $all_emails),
		));
		$this->parse('mail_all_block', 'mail_all');
	}
	else {
		$this->setVar('mail_all_block', '');
	}

	$waitingList = $groupHandler->getWaitingList($category['id']);
	if (is_array($waitingList)) {
		foreach ($waitingList as $waiting) {
			$output = array('WAITING_NAME' => $userHandler->formatName($waiting),
							'WAITING_ID' => $waiting['uid'],

							);
			$this->setVar($output);
			$this->parse("waiting_block", "waiting", true);
		}
		$this->parse("authorisation_block", "authorisation");

	} else
		$this->setVar("authorisation_block", "");

	/* The currently-logged in user and the current category owner
	 * Use for comparing and summoning a warning message that you
	 * could lose control
	 */
	$this->setVar(array(
		'MAKEADMIN_CURRENT_USER_ID' => $_SESSION['user']->getId(),
		'MAKEADMIN_CURRENT_ADMIN_ID' => $category['owner'],
		'MAKEADMIN_SUPER' => isset($_SESSION['su']) ? $_SESSION['su'] : 0
	));

} else
	error(2);

?>
