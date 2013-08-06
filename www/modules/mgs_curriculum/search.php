<?php
$groupHandler = new Group;

if($groupHandler->isStaff() )
   $this->setFile(TEMPLATE_content, 'modules/mgs_curriculum/search.html');
else
   error(19);
?>