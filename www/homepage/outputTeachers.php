<?php
// gets teachers involved in subject, outputs links to their addressbook page
$this->setBlock($componentHandle, "staff_teachers", "staff_teacher_block");
$this->setVar('staff_teacher_block', '');

if (($code != '') && Cfg::get('external_enable')) {
	require_once SITE_root.'/library/user.php';

	$externalClassHandler = new External_Class();
	$userHandler = new User();

	$listSyns = $externalClassHandler->getTeacherName($code);

	if (is_array($listSyns)) {
		foreach($listSyns as $synid) {
			$teacher = $userHandler->getBySynergy($synid);

			if (is_array($teacher)) {
				$name = $userHandler->formatName($teacher);

				$this->setVar('MEMBER_NAME', "<a href=\"/search/user/{$teacher['uid']}\">{$name}</a>");
				$this->parse("staff_teacher_block", "staff_teachers", true);
			}

			$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		}
	}
}
?>