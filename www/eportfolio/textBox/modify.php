<?php
$page = new SitePage();
$textBoxHandler = new Eportfolio_Textbox();

$textBox = $textBoxHandler->getByComponentLocationID($this->arg[1]);

$this->setFile(TEMPLATE_content, 'eportfolio/components/textBox-modify.html');

$this->setVar(array(
	'USER_ID' => $this->arg[0],
	'COMPONENT_LOCATION_ID' => $this->arg[1],
	'TEXTBOX_ID' => $textBox['textBoxID'],
	'TEXTBOX_HEADING' => $textBox['heading'],
	'TEXTBOX_BODY' => $textBox['body'],
));