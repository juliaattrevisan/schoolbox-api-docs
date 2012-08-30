<?php
$this->setBlock($componentHandle, "member_email", "member_email_block");
$this->setBlock($componentHandle, "person", "person_block");
$this->setBlock('person', 'has_portrait', 'has_portrait_block');
$this->setBlock('person', 'has_group', 'has_group_block');
$this->setBlock('config', 'displayportraits', 'displayportraits_block');

$this->setVar('displayportraits_block', '');
$this->setVar('has_portrait_block', '');
$this->setVar('has_group_block', '');
$this->setVar('person_block', '');

/* Display options
 */
$chosenStyle = array(
	'name' => 'Grid',
	'value' => 'grid',
	'css' => 'thumb',
);

$allOptions = array(
	$chosenStyle,
	array(
		'name' => 'List',
		'value' => 'list',
		'css' => 'bar',
	),
);

foreach($allOptions as $currentOption) {
	$this->setVar(array(
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_VALUE' => $currentOption['value'],
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_NAME' => $currentOption['name'],
		'COMPONENT_CONFIG_DISPLAYPORTRAITS_SELECTED' => $currentOption['value'] == $config['displayPortraits'] ? ' selected="selected"' : '',
	));

	if ($currentOption['value'] == $config['displayPortraits']) {
		$chosenStyle = $currentOption;
	}

	$this->parse('displayportraits_block', 'displayportraits', true);
}

$userHandler = new User();
$userList = $userHandler->getAllInCategory($categoryID);
$emailObj = new Email();

if (is_array($userList)) {
	foreach ($userList as $user) {
		$name = $userHandler->formatName($user);
		$email = $emailObj->formatEmailFromArray($user);

		if ($user['type'] == 3) {
			$name .= " (Group Admin.)";
		}

		if ($email) {
			$emailall .= $email . ",";
		}

		$this->setVar(array(
			'PERSON_UID' => $user['uid'],
			'PERSON_NAME' => $name,
			'PERSON_EXTERNAL_ID' => $user['synergy_id'],
			'PERSON_GROUP' => $meta['gname'],
			'LIST_STYLE' => $chosenStyle['css'],
		));

		/* Don't display images in list view
		 */
		if ($chosenStyle['value'] == 'grid') {
			$this->parse('has_portrait_block', 'has_portrait');
			$this->parse('has_group_block', 'has_group');
		}
		else {
			$this->setVar('has_portrait_block', '');
			$this->setVar('has_group_block', '');
		}

		$this->parse('person_block', 'person', true);

		/* Clear children after parsing out the parent
		 */
		$this->setVar('has_portrait_block', '');
		$this->setVar('has_group_block', '');
	}

	$this->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
	$this->setVar("EMAILALL", $emailall);
	$this->parse("member_email_block", "member_email");
}
else {
	$this->setVar('member_email_block','');
}
