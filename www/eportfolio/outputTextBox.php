<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-textBox.php';

$textBoxHandler = new ePortfolioTextBox;

$textBox = $textBoxHandler->getByID((int)$config['textBoxID']);

/* Don't show deleted items
 */
if ($textBox['deleted'] == 'true') {
	unset($textBox);
}

$this->setBlock($componentHandle, 'text_editable', 'text_editable_block');
$this->setBlock($componentHandle, 'text_locked', 'text_locked_block');
$this->setBlock('text_locked', 'modify_text', 'modify_text_block');

/* Set tokens for the textbox
 */
$this->setVar(array(
    'TEXTBOX_HEADING' => !empty($textBox['heading']) ? scrubText($textBox['heading']) : 'Text Box',
    'TEXTBOX_BODY' => scrubHTML($textBox['body']),
    'TEXTBOX_ID' => $textBox['textBoxID'],
));

$this->setVar('text_editable_block', '');
$this->setVar('modify_text_block', '');
$this->setVar('text_locked_block', '');

if ($eportfolioHandler->canEdit($this->arg[0]) && ($this->arg[1] == 'textBoxModify')) {
	$this->parse('text_editable_block', 'text_editable');
}
else if ($eportfolioHandler->canEdit($this->arg[0]) && ($this->arg[1] == 'textBoxCreate')) {
	$this->setVar(array(
        'TEXTBOX_HEADING' => '',
        'TEXTBOX_BODY' => '',
        'TEXTBOX_ID' => '',
    ));

	$this->parse('text_editable_block', 'text_editable');
}
else {

	/* Modify links
     */
	if ($eportfolioHandler->canEdit($this->arg[0])) {
		$this->parse('modify_text_block', 'modify_text');
    }
	
	/* Formatted text
     */
	$this->parse('text_locked_block', 'text_locked');
}

$current = $textBox;

/* textbox selection */
$this->setBlock("config", "select", "select_block".$componentCount);
$textBoxList = $textBoxHandler->getAllByUser($userID);

if (is_array($textBoxList)) {
	foreach ($textBoxList as $textBox) {
		$output = array('COMPONENT_CONFIG_SELECT_NAME' 		=> scrubText($textBox['heading']),
						'COMPONENT_CONFIG_SELECT_VALUE' 	=> $textBox['textBoxID'],
						'COMPONENT_CONFIG_SELECT_SELECTED'	=> $textBox['textBoxID'] == $current['textBoxID'] ? 'selected="selected"' : '',
						);
		$this->setVar($output);
		$this->parse("select_block".$componentCount, "select", true);
	}
}


?>