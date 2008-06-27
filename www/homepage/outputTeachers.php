<?php
// gets teachers involved in subject, outputs links to their addressbook page
require_once '../library/synergetic-class.php';
require_once '../library/user.php';
$synergeticClass = new SynergeticClass;
$userHandler = new User;

$this->setBlock($componentHandle, "staff_teachers", "staff_teacher_block");

if($code != "" && SYNERGY_enable)
{	
	$listSyns = $synergeticClass->getTeacherName($code);
	if(is_array($listSyns)) {
		foreach($listSyns as $synid) {
			$teacher = $user->getBySynergyID($synid);
			if(is_array($teacher)) {
				$name = formatName($teacher);
				$this->setVar('MEMBER_NAME', "<a href=\"/search/userLookup.php?id={$teacher['id']}\">{$name}</a>");
				$this->parse("staff_teacher_block", "staff_teachers", true);	
			}
		}
	}
}
else 
	$this->setVar('staff_teacher_block', '');	
?>