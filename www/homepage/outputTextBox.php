<?php
require_once(SITE_root . '/library/textBox.php');

$this->setBlock($componentHandle, 'text_editable', 'text_editable_block');
$this->setBlock($componentHandle, 'text_locked', 'text_locked_block');
$this->setBlock('text_locked', 'modify_text', 'modify_text_block');

$textBoxHandler = new TextBox;
$textBox = $textBoxHandler->getByID((int)$config['textBoxID']);

/* Dont show deleted items
 */
if ($textBox['deleted'] == 'true') {
	unset($textBox);
}

$output = array(
	'TEXTBOX_HEADING' => !empty($textBox['heading']) ? Scrub::text($textBox['heading']) : 'Text Box',
	'TEXTBOX_BODY' => Scrub::html($textBox['body']),
	'TEXTBOX_ID' => $textBox['textBoxID'],
);

$this->setVar($output);

$this->setVar('text_editable_block', '');
$this->setVar('modify_text_block', '');
$this->setVar('text_locked_block', '');

if (($permission >= WRITE_ACCESS) && ($this->arg[1] == 'textBoxModify') && ($this->arg[2] == $component['homepageComponentLocationID'])) {
	$this->parse('text_editable_block', 'text_editable');
}
else if ($permission >= WRITE_ACCESS && $this->arg[1] == 'textBoxCreate' && $this->arg[2] == $component['homepageComponentLocationID']) {
	$output = array(
		'TEXTBOX_HEADING' => '',
		'TEXTBOX_BODY' => '',
		'TEXTBOX_ID' => '',
	);

	$this->setVar($output);
	$this->parse('text_editable_block', 'text_editable');
}
else {

	/* modify links */
	if ($permission >= WRITE_ACCESS) {
		$this->parse('modify_text_block', 'modify_text');
	}

	/* formatted text */
	$this->parse('text_locked_block', 'text_locked');
}

$current = $textBox;

/* textbox selection */
$this->setBlock("config", "select", "select_block".$component['homepageComponentLocationID']);
$textBoxList = $textBoxHandler->getAllByCategory($categoryID);

if (is_array($textBoxList)) {
	foreach ($textBoxList as $textBox) {
		$output = array(
			'COMPONENT_CONFIG_SELECT_NAME' => Scrub::text($textBox['heading']),
			'COMPONENT_CONFIG_SELECT_VALUE' => $textBox['textBoxID'],
			'COMPONENT_CONFIG_SELECT_SELECTED' => $textBox['textBoxID'] == $current['textBoxID'] ? 'selected="selected"' : '',
		);

		$this->setVar($output);
		$this->parse("select_block" . $component['homepageComponentLocationID'], "select", true);
	}
}
