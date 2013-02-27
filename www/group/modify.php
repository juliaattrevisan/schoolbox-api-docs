<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';

$this->setFile(TEMPLATE_content, 'group/modify.html');
$this->setBlock(TEMPLATE_content, 'member', 'member_block');
$this->setBlock('member', 'make_owner', 'make_owner_block');

$this->setBlock(TEMPLATE_content, 'mail_all', 'mail_all_block');

$this->setBlock(TEMPLATE_content, "authorisation", "authorisation_block");
$this->setBlock("authorisation", "waiting", "waiting_block");

$this->setTitle('Modify User Group');
$this->setTab('resources');

$groupHandler = new Group(HOMEPAGE_SERVICE);
$categoryHandler = new Category;

if (is_numeric($this->arg[0])) {
	$userHandler = new User();

	$category = $categoryHandler->getByID($this->arg[0]);

	// check access
	if($groupHandler->getPermission($this->arg[0]) < FULL_ACCESS)
		error(19);

	$output = array('CATEGORY_NAME'		=> Scrub::text($category['name']),
					'CATEGORY_ID'		=> $category['id'],
					'GROUP_OWNER'		=> $userHandler->formatName($category),
					'OWNER_ID'			=> $category['owner'],
					'GROUP_TYPE_1'		=> '',
					'GROUP_TYPE_2'		=> '',
					'GROUP_TYPE_3'		=> '',
					'GROUP_TYPE_'.$category['type']	=> 'checked="checked"',
					);

	$this->setVar($output);

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
			$output = array('USER_NAME'		=> $userHandler->formatName($member),
							'USER_JAVA'		=> addslashes($userHandler->formatName($member)),
							'USER_ID'		=> $member['uid'],
							'GROUP_CHECKED'	=> $member['type'] > 1 ? 'checked=\'checked\'' : ''
							);

			$this->setVar($output);


			$this->setVar('make_owner_block', '');
			if ($groupHandler->allowOwner($member['uid']))
				$this->parse('make_owner_block', 'make_owner');


			$this->parse('member_block', 'member', true);
		}

	}
	else {
		$this->setVar('member_block', '');
	}

	//
	// Set up the email all block (PHPLib sucks)
	if (!empty($all_emails)) {
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



} else
	error(2);

?>
