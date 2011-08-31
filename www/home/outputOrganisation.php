<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$this->setBlock($component['name'], 'extras_staff', 'extras_staff_block');

$groupHandler = new Group;

if($groupHandler->isStaff()) {
    $this->parse('extras_staff_block', 'extras_staff');
}
else {
    $this->setVar('extras_staff_block','');
}

$this->parse('POS'.$component['position'], $component['name']);

?>
