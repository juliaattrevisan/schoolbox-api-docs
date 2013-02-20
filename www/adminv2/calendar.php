<?php
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/calendar.html');
$this->setTitle('Admin - Calendar');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'calendar');

$userHandler = new User();

$this->checkList('campus_check_list', $userHandler->getCampusList(), '', TEMPLATE_content, array('id'=>'CAMPUSID','name'=>'CAMPUSNAME'));
$this->checkList('user_group_check_list', $userHandler->getGroupList(), '', TEMPLATE_content, array('id'=>'USERGROUPID','name'=>'USERGROUPNAME'));

/* Stolen from settings
 */
$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');
$this->setBlock(TEMPLATE_content, 'import', 'import_block');
$this->setBlock(TEMPLATE_content, 'subscribe', 'subscribe_block');

$this->setVar('import_block', '');
$this->setVar('subscribe_block', '');

if ($this->arg[0]) {
	$selectedType = urldecode($this->arg[0]);
}
else {
	$selectedType = 'Site';
}

$typeList = array(
	array(
		'type' => 'Import'
	),
	array(
		'type' => 'Subscribe'
	),
);

if (is_array($typeList)) {
	foreach ($typeList as $type) {
		/* Navigation stuff
 		 */
		$output = array(
			'SETTING_TAB_NAME' => $type['type'],
			'SETTING_TAB_URL' => urlencode($type['type']),
			'SETTING_TAB_CURRENT' => '',
		);

		$this->setVar($output);

		if ($selectedType == $type['type']) {
			$this->setVar('SETTING_TAB_CURRENT', 'current');
		}

		$this->parse('setting_tab_block', 'setting_tab', true);

		/* Individual pages
		 */
		if (($type['type'] == 'Import') && ($selectedType == 'Import')) {
			$this->parse('import_block', 'import');
		}
		else {

			/* All calendar subscription options go here
			 */

			$this->parse('subscribe_block', 'subscribe');
		}
	}
}