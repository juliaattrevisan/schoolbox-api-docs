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

?>
