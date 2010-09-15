<?php

if(is_array($_SESSION['subjects'])) {
    foreach($_SESSION['subjects'] as $subject) {
        if(stristr($subject['code'],'HR' ) || stristr($subject['code'],'EN' )) {
            $homeroom = $subject['code'];
            break;
        }
    }
}

if(empty($homeroom))
	$homeroom = $_SESSION['subjects'][0]['code'];

$this->setVar('MY_CLASS', $homeroom);
$this->parse('POS'.$component['position'], $component['name']);

?>