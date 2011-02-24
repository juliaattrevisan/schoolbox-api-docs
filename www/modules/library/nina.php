<?php
$this->setFile(TEMPLATE_content, 'modules/library/nina.html');

require_once SITE_root.'/library/textBox.php';
$textBoxHandler = new TextBox;

$or=$textBoxHandler->getByID(530);
$rr= $textBoxHandler->getByID(531);
$nb=$textBoxHandler->getByID(537);
$qf=$textBoxHandler->getByID(532);
$itn=$textBoxHandler->getByID(534);
$rt=$textBoxHandler->getByID(535);
$cv=$textBoxHandler->getByID(536);

$this->setVar('ONLINE_RESOURCES',scrubHTML($or['body']) );
$this->setVar('REMARKABLE_READING', scrubHTML($rr['body']) );
$this->setVar('NEW_BOOKS', scrubHTML($nb['body']) );
$this->setVar('QUICK_FACTS', scrubHTML($qf['body']) );
$this->setVar('IN_THE_NEWS', scrubHTML($itn['body']) );
$this->setVar('RESEARCH_TOOLS',scrubHTML($rt['body']) );
$this->setVar('CLICK_VIEW',scrubHTML($cv['body']) );



?>
