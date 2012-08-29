<?php
$this->setBlock($componentHandle, 'student', 'student_block');
$this->setBlock($componentHandle, 'student_email', 'student_email_block');

$this->setBlock($componentHandle, 'parent', 'parent_block');
$this->setBlock($componentHandle, 'parent_email', 'parent_email_block');

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
				'STUDENT_ID' => $user['uid'],
				'STUDENT_EXTERNAL_ID' => $user['synergy_id'],
				'STUDENT_NAME' => $name,
				'ON_OFF' => ++$j%2? '' : 'dark',
			));

			$this->parse('student_block', 'student', true);
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
