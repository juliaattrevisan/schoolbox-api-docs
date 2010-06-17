<?php
// gets teachers involved in subject, outputs links to their addressbook page
$this->setBlock($componentHandle, "staff_teachers", "staff_teacher_block");
$this->setVar('staff_teacher_block', '');
if($code != "" && SYNERGY_enable)
{	
	require_once '../library/synergetic-class.php';
	require_once '../library/user.php';
	$synergeticClass = new SynergeticClass;
	$userHandler = new User;
	
	$listSyns = $synergeticClass->getTeacherName($code);
	if(is_array($listSyns)) {
		foreach($listSyns as $synid) {
			$teacher = $userHandler->getBySynergy($synid);
			if(is_array($teacher)) {
				$name = formatName($teacher);
				$this->setVar('MEMBER_NAME', "<a href=\"/search/user/{$teacher['uid']}\">{$name}</a>");
				$this->parse("staff_teacher_block", "staff_teachers", true);	
			}
			$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		}
	}
}
?>