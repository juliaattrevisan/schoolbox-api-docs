<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-textBox.php';

$textBoxHandler = new ePortfolioTextBox;

$textBox = $textBoxHandler->getByField('componentLocationID', $component['eportfolioComponentLocationID']);

/* Set tokens for the textbox
 */
$this->setVar(array(
    'TEXTBOX_HEADING' => !empty($textBox['heading']) ? scrubText($textBox['heading']) : 'Text Box',
    'TEXTBOX_BODY' => scrubHTML($textBox['body']),
    'TEXTBOX_ID' => $textBox['textBoxID'],
));