<?php

foreach($_SESSION['subjects'] as $subject) {
	if(stristr($subject['code'],'HR' ))
		$homeroom = $subject['code'];
}

if(empty($homeroom))
	$homeroom = $_SESSION['subjects'][0]['code'];

$this->setVar('MY_CLASS', $homeroom);
$this->parse('POS'.$component['position'], $component['name']);

?>