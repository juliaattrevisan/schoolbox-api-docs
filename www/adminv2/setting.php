<?php

require_once SITE_root . '/include/config.php';

$this->setFile(TEMPLATE_content, 'adminv2/settings.html');
$this->setTitle('Admin - Settings');

$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');

$this->setBlock(TEMPLATE_content, 'setting_type_noneditable', 'setting_type_noneditable_block');
$this->setBlock(TEMPLATE_content, 'setting_type_input', 'setting_type_input_block');
$this->setBlock(TEMPLATE_content, 'setting_type_textarea', 'setting_type_textarea_block');
$this->setBlock(TEMPLATE_content, 'setting_type_boolean', 'setting_type_boolean_block');
$this->setBlock(TEMPLATE_content, 'setting_type_select', 'setting_type_select_block');
$this->setBlock('setting_type_select', 'setting_select','setting_select_block');
$this->setBlock(TEMPLATE_content, 'setting_type_check', 'setting_type_check_block');

$output = array(
	'setting_type_noneditable_block' => '',
	'setting_type_input_block' => '',
	'setting_type_textarea_block'   => '',
	'setting_type_boolean_block'    => '',
	'setting_type_select_block' => '',
	'setting_type_check_block'  => '',
);

$this->setVar($output);

if ($this->arg[0]) {
    $selectedType = urldecode($this->arg[0]);
}
else {
    $selectedType = 'Site';
}

$configHandler = new Config;
$typeList = $configHandler->getTypeList();

if (is_array($typeList)) {
    foreach($typeList as $type) {

        $output = array('SETTING_TAB_NAME'      => $type['type'],
                        'SETTING_TAB_URL'       => urlencode($type['type']),
                        'SETTING_TAB_CURRENT'   => '',
                        );
        $this->setVar($output);

        if($selectedType==$type['type']) {
                $this->setVar('SETTING_TAB_CURRENT', 'current');
        }

        $this->parse('setting_tab_block','setting_tab',true);
    }
}

$optionList = $configHandler->getAll('', $selectedType);

foreach($optionList as $option) {

    $output = array(
		'SETTING_TITLE' => $option['title'],
		'SETTING_DESC'  => $option['description'],
		'SETTING_NAME'  => $option['name'],
		'SETTING_VALUE' => $option['value'],
	);

    $this->setVar($output);

	if ($option['editable'] == 'false') {
		$this->parse('setting_type_noneditable_block', 'setting_type_noneditable', true);
	}
	else {
		switch ($option['field']) {
			case 'input':
			   $this->parse('setting_type_input_block','setting_type_input',true);
			break;

			case 'textarea' :
				$this->parse('setting_type_input_block','setting_type_input',true);
			break;

			case 'boolean' :
				if ($option['value'] == 1) {
					$checked = 'checked="checked"';
				}
				else {
					$checked = '';
				}

				$this->setVar('SETTING_CHECKED', $checked);
				$this->parse('setting_type_input_block','setting_type_boolean',true);

			break;

			case 'select':
				$optionList = explode(':',$option['options']);
                $this->setVar('setting_select_block', '');
                foreach($optionList as $opt) {
                    $this->setVar('SELECT_OPTION', $opt);
                    $this->setVar('SETTING_OPTION_SELECTED', $option['value']==$opt?'selected="selected"':'');
                    $this->parse('setting_select_block', 'setting_select',true);
                }
                $this->parse('setting_type_input_block','setting_type_select',true);
			break;

			case 'checkbox':
				$this->parse('setting_type_input_block','setting_type_checkbox',true);
			break;
		}
	}
}
?>