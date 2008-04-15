<?php

$this->setBlock($component['name'], "student_block", "student_subjects");
$this->setBlock("student_block", "subject_block", "subject_item");

if(is_array($_SESSION['children'])) {
	foreach($_SESSION['children'] as $child)
	{
		$query = "SELECT title, firstname, lastname, year FROM user WHERE id = {$child['id']}";
		$user = $this->db->getRow($query);
		$this->setVar("STUDENT_ID", $child['id']);
		$this->setVar("STUDENT_NAME", formatName($user,'{firstname} {lastname}'));
		$this->setVar("STUDENT_YEAR", $user['year']);
		
		if(is_array($child['subjects']) ) {
			foreach($child['subjects'] as $subject)
			{
				$this->setVar("STUDENT_SUBJECT_NAME", $subject['name']);
				$this->setVar("STUDENT_SUBJECT_CODE", $subject['code']);
				$this->parse("subject_item", "subject_block", true);
			}
		}

		$this->parse("student_subjects", "student_block", true);
		$this->setVar("subject_item", '');
	
	}
	$this->parse('POS'.$component['position'], $component['name']);
}
else
	$this->setVar('POS'.$component['position'], '');

?>
