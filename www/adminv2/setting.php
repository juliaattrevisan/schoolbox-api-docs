<?php

require_once SITE_root . '/include/config.php';

$this->setFile(TEMPLATE_content, 'adminv2/settings.html');

$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');

$this->setBlock(TEMPLATE_content, 'setting_type_input', 'setting_type_input_block');
$this->setBlock(TEMPLATE_content, 'setting_type_textarea', 'setting_type_textarea_block');
$this->setBlock(TEMPLATE_content, 'setting_type_boolean', 'setting_type_boolean_block');
$this->setBlock(TEMPLATE_content, 'setting_type_select', 'setting_type_select_block');
$this->setBlock(TEMPLATE_content, 'setting_type_check', 'setting_type_check_block');

$output = array('setting_type_input_block' => '',
                'setting_type_textarea_block'   => '',
                'setting_type_boolean_block'    => '',
                'setting_type_select_block' => '',
                'setting_type_check_block'  => '',);
$this->setVar($output);

$configHandler = new Config;

if($this->arg[0])
    $selectedType = urldecode($this->arg[0]);
else
    $selectedType = 'Site';

$typeList = $configHandler->getTypeList();
if(is_array($typeList)) {
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

    $output = array('SETTING_TITLE' => $option['title'],
                    'SETTING_DESC'  => $option['description'],
                    'SETTING_NAME'  => $option['name'],
                    'SETTING_VALUE' => $option['value'],);
    $this->setVar($output);

    switch ($option['field']) {
        case 'input':
           $this->parse('setting_type_input_block','setting_type_input',true);
        break;

        case 'textarea' :
            $this->parse('setting_type_input_block','setting_type_input',true);
        break;

        case 'boolean' :
            $this->setVar('SETTING_VALUE', $option['value']==1?'checked="checked':'');
            $this->parse('setting_type_input_block','setting_type_boolean',true);

        break;

        case 'select':
            $this->parse('setting_type_input_block','setting_type_select',true);
        break;

        case 'checkbox':
            $this->parse('setting_type_input_block','setting_type_checkbox',true);
        break;
    }

}


?>