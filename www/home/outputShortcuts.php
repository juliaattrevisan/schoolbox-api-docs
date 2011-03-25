<?php
$this->setBlock($component['name'], 'student_only', 'student_only_block');
$this->setBlock($component['name'], 'staff_only', 'staff_only_block');
$this->setVar('staff_only_block','');
$this->setVar('student_only_block','');

if($groupHandler->isStaff())
    $this->parse('staff_only_block','staff_only');
else if($groupHandler->isStudent())
    $this->parse('student_only_block', 'student_only');

$this->parse('POS'.$component['position'], $component['name']);
?>
