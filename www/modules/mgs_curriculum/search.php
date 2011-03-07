<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
$groupHandler = new Group;

if($groupHandler->isStaff() )
   $this->setFile(TEMPLATE_content, 'modules/mgs_curriculum/search.html');
else
   error(19);
?>