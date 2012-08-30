<?php
$this->setBlock($componentHandle, 'person', 'person_block');
$this->setBlock($componentHandle, 'student_email', 'student_email_block');
$this->setBlock($componentHandle, 'parent', 'parent_block');
$this->setBlock($componentHandle, 'parent_email', 'parent_email_block');

$this->setBlock('config', 'displayportraits', 'displayportraits_block');
$this->setBlock('person', 'has_portrait', 'has_portrait_block');
$this->setBlock('person', 'has_group', 'has_group_block');

$this->setVar('displayportraits_block', '');
$this->setVar('has_portrait_block', '');
$this->setVar('has_group_block', '');
$this->setVar('person_block', '');

/* Display options
 */
$allOptions = array(
	array(
		'name' => 'Grid',
		'value' => 'grid',
		'css' => 'thumb',
	),
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

$categoryHandler = new Category();
$category = $categoryHandler->getByID($categoryID);

/* Get all the students belonging to this category code
 */
$externalClassHandler = new External_Class();
$allStudents = $externalClassHandler->getStudents($category['code']);

$userHandler = new User();
$emailHandler = new Email();

$allStudentEmails = array();

if (is_array($allStudents)) {
	foreach ($allStudents as $currentStudent) {
		$user = $userHandler->getBySynergy($currentStudent['synergy_id']);

		/* Get the schoolbox user and process it if it exists
		 */
		if (is_array($user)) {
			$name = $userHandler->formatName($user);
			$email = $emailHandler->formatEmailFromArray($user);

			/* Accumulate this for a giant parent-based bcc
			 */
			$synergyIds[] = $currentStudent['synergy_id'];

			/* Agglutinate a giant bcc
			 */
			if ($email) {
				$allStudentEmails[]= $email;
			}

			$this->setVar(array(
				'PERSON_UID' => $user['uid'],
				'PERSON_EXTERNAL_ID' => $user['synergy_id'],
				'PERSON_NAME' => $name,
				'PERSON_GROUP' => $user['gname'],
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
	}

	$emailAllStudents = implode(',', $allStudentEmails);

	/* Student bulk email
	 */
	$this->setVar(array(
		'REPLY_TO' => $emailHandler->formatEmailFromArray($_SESSION),
		'EMAIL_ALL_STUDENTS' => $emailAllStudents,
	));

	$this->parse('student_email_block', 'student_email');

	/* Parent bulk email
	 */
	if (count($synergyIds)) {
		$allParentEmails = array();
		$allSynergyIds = implode(',', $synergyIds);

		$externalHandler = new External_User();
		$allParents = $externalHandler->getParentsOf($allSynergyIds);

		if (is_array($allParents) && count($allParents)) {
			foreach($allParents as $currentParent) {
				$email = trim($currentParent['StudentContactDefaultEmail']);

				if (strlen($email)) {
					$allParentEmails[] = $email;
				}
			}

			/* Uniquify emails
			 */
			$emailAllParents = implode(', ', array_filter(array_unique($allParentEmails)));
		}

		$this->setVar(array(
			'REPLY_TO' => $emailHandler->formatEmailFromArray($_SESSION),
			'EMAIL_ALL_PARENTS' => $emailAllParents,
		));

		$this->parse('parent_email_block', 'parent_email');
	}
}
else {
	$this->setVar('student_block', '');
	$this->setVar('student_email_block','');
}
