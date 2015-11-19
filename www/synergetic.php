<?php

$this->setFile(TEMPLATE_content, 'synergetic.html');
$this->setTitle('School Records');

if(preg_match('/^\w+$/',$this->arg[0]))  {
    $this->setVar('PAGE_REQUEST', $this->arg[0]);
    $this->setVar('SYNERGETIC_MENU', $this->arg[1]);
    $this->setVar('ID_VALUE', $_GET['id']);
} else {
    $this->setVar('PAGE_REQUEST', '');
    $this->setVar('SYNERGETIC_MENU', '');
    $this->setVar('ID_VALUE', '');
}
