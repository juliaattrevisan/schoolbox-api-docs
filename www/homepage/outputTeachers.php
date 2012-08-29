<?php
// gets teachers involved in subject, outputs links to their addressbook page
$this->setBlock($componentHandle, "staff_teachers", "staff_teacher_block");
$this->setVar('staff_teacher_block', '');

if (($code != '') && Cfg::get('external_enable')) {
	require_once SITE_root.'/library/user.php';

	$externalClassHandler = new External_Class();
	$userHandler = new User();
    $groupHandler = new Group();

	$listSyns = $externalClassHandler->getTeacherName($code);
    $staffGroups = $groupHandler->getStaffGroupTypeIds();

	if (is_array($listSyns)) {
		foreach($listSyns as $synid) {
			$teacher = $userHandler->getBySynergyAndGroup($synid, $staffGroups);

			if (is_array($teacher)) {
				$name = $userHandler->formatName($teacher);

				$this->setVar(array(
					'MEMBER_NAME' => "<a href=\"/search/user/{$teacher['uid']}\">{$name}</a>",
					'STAFF_NAME' => $teacher['firstname'] . ' ' . $teacher['lastname'],
					'STAFF_EXTERNAL_ID' => $synid,
				));

				$this->parse("staff_teacher_block", "staff_teachers", true);
			}

			$this->setVar('ON_OFF', ++$j%2? '' : 'dark');
		}
	}
}
?>
