<?php
$textBoxHandler = new Eportfolio_Textbox();

$textBox = $textBoxHandler->getByField('componentLocationID', $component['eportfolioComponentLocationID']);

/* Set tokens for the textbox
 */
$this->setVar(array(
    'TEXTBOX_HEADING' => !empty($textBox['heading']) ? Scrub::text($textBox['heading']) : 'Text Box',
    'TEXTBOX_BODY' => Scrub::html($textBox['body']),
    'TEXTBOX_ID' => $textBox['textBoxID'],
));