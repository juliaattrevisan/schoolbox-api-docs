<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
	error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/settings.html');
$this->setTitle('Admin - Settings');

$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');
$this->setBlock(TEMPLATE_content, 'setting', 'setting_block');
$this->setBlock('setting', 'setting_type_noneditable', 'setting_type_noneditable_block');
$this->setBlock(TEMPLATE_content, 'setting_group_localisation', 'setting_group_localisation_block');
$this->setBlock(TEMPLATE_content, 'setting_group_skin_config', 'setting_group_skin_config_block');
$this->setBlock('setting', 'setting_type_input', 'setting_type_input_block');
$this->setBlock('setting', 'setting_type_textarea', 'setting_type_textarea_block');
$this->setBlock('setting', 'setting_type_html', 'setting_type_html_block');
$this->setBlock('setting', 'setting_type_boolean', 'setting_type_boolean_block');
$this->setBlock('setting', 'setting_type_select', 'setting_type_select_block');
$this->setBlock('setting_type_select', 'setting_select', 'setting_select_block');
$this->setBlock('setting', 'setting_type_colour', 'setting_type_colour_block');
$this->setBlock('setting', 'setting_type_file', 'setting_type_file_block');
$this->setBlock('setting', 'setting_type_list', 'setting_type_list_block');
$this->setBlock('setting', 'setting_type_resource', 'setting_type_resource_block');
$this->setBlock(TEMPLATE_content, 'setting_save', 'setting_save_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'setting');

$this->setVar('setting_block', '');

if ($this->arg[0]) {
	$selectedType = urldecode($this->arg[0]);
}
else {
	$selectedType = 'Site';
}

$typeList = Cfg::getTypeList();

if (is_array($typeList)) {
	foreach ($typeList as $type) {
		$output = array(
			'SETTING_TAB_NAME' => $type['type'],
			'SETTING_TAB_URL' => urlencode($type['type']),
			'SETTING_TAB_ACTIVE' => '',
		);

		$this->setVar($output);

		if ($selectedType == $type['type']) {
			$this->setVar('SETTING_TAB_ACTIVE', 'active');
		}

		$this->parse('setting_tab_block', 'setting_tab', true);
	}
}

$optionList = Cfg::getAllOfType('configID', $selectedType);
$editable = false;

if(is_array($optionList)) {
    foreach ($optionList as $option) {
        $output = array(
            'SETTING_TITLE' => Scrub::text($option['title']),
            'SETTING_DESC'  => Scrub::text($option['description']),
            'SETTING_NAME'  => Scrub::text($option['name']),
            'SETTING_VALUE' => Scrub::text($option['value']),
            'setting_type_noneditable_block' => '',
            'setting_type_input_block'       => '',
            'setting_type_textarea_block'    => '',
            'setting_type_html_block'        => '',
            'setting_type_boolean_block'     => '',
            'setting_type_select_block'      => '',
            'setting_type_list_block'        => '',
            'setting_type_resource_block'    => '',
            'setting_type_colour_block'      => '',
            'setting_type_file_block'      => '',
            'setting_group_localisation_block' => '',
            'setting_group_skin_config_block' => '',
        );

        $this->setVar($output);

        if ($option['hidden'] == 'true') {
            continue;
        }
        elseif ($option['editable'] == 'false') {
            $this->parse('setting_type_noneditable_block', 'setting_type_noneditable', true);
        }
        else {
            $editable = true;
            /* Decide whether this option should have
             * javascript validation enforced
             */
            if ($option['required'] == 'true') {
                $this->setVar(array(
                    'OPTION_REQUIRED' => 'required'
                ));
            }
            else {
                $this->setVar(array(
                    'OPTION_REQUIRED' => ''
                ));
            }

            switch ($option['field']) {
                case 'input':
                    $this->parse('setting_type_input_block', 'setting_type_input', true);
                    break;

                case 'textarea' :
                    $this->parse('setting_type_textarea_block', 'setting_type_textarea', true);
                    break;

                case 'html' :
                    $this->parse('setting_type_html_block', 'setting_type_html', true);
                    break;

                case 'resource':
                    $this->parse('setting_type_resource_block', 'setting_type_resource', true);
                    break;

                case 'checkbox':
                case 'boolean' :
                    if ($option['value'] == 1) {
                        $checked = 'checked="checked"';
                    }
                    else {
                        $checked = '';
                    }

                    $this->setVar('SETTING_CHECKED', $checked);
                    $this->parse('setting_type_input_block', 'setting_type_boolean', true);

                    break;

                case 'select':
                    $optionList = explode(':', $option['options']);
                    $this->setVar('setting_select_block', '');
                    foreach ($optionList as $opt) {
                        $this->setVar('SELECT_VALUE', $opt);
                        $this->setVar('SELECT_OPTION', $opt);
                        $this->setVar('SETTING_OPTION_SELECTED', $option['value'] == $opt ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case 'selectKey':
                    $optionList = explode(':', $option['options']);
                    $this->setVar('setting_select_block', '');
                    foreach ($optionList as $opt) {
                        list($key, $value) = explode(';', $opt);
                        $this->setVar('SELECT_VALUE', $key);
                        $this->setVar('SELECT_OPTION', $value);
                        $this->setVar('SETTING_OPTION_SELECTED', $option['value'] == $key ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case 'selectLookup':
                    $this->setVar('setting_select_block', '');
                    // options: expected to contain mapper
                    $lookupOptions  = unserialize($option['options']);
                    $mapper         = call_user_func(array($lookupOptions['mapper'], 'getInstance'));
                    $getKeyMethod   = $lookupOptions['getKeyMethod']   ?: 'getId';
                    $getValueMethod = $lookupOptions['getValueMethod'] ?: 'getName';
                    $findMethod     = $lookupOptions['findMethod']     ?: 'findAll';
                    foreach (call_user_func(array($mapper, $findMethod)) as $opt) {
                        $key   = $opt->$getKeyMethod();
                        $value = $opt->$getValueMethod();
                        $this->setVar('SELECT_VALUE', $key);
                        $this->setVar('SELECT_OPTION', $value);
                        $this->setVar('SETTING_OPTION_SELECTED', $option['value'] == $key ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case 'bytes':
                    $this->setVar('SETTING_VALUE', Alaress_Byte_Format::bytesToString($option['value']));
                    $this->parse('setting_type_input_block', 'setting_type_input', true);
                    break;

                case 'list':

                    break;

                case 'colour':
                    $this->parse('setting_type_colour_block', 'setting_type_colour', true);
                    break;

                case 'file':
                    $zone = new \Schoolbox\Resource\Attachment\Zone(Scrub::text($option['name']));
                    $zone->setThumbnailSize(64);
                    $zone->setAttachPrompt('Attach '. Scrub::text(strtolower($option['title'])));
                    $zone->setAttachmentsLabel('');
                    $zone->setFileVar(Scrub::text($option['name']));
                    $zone->setMimeFilter('image/*');
                    $zone->setModalTitle('Select '. Scrub::text(strtolower($option['title'])));
                    if($option['value']) {
                        $zone->attachFiles(array($option['value']));
                    }
                    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');
                    $this->parse('setting_type_file_block', 'setting_type_file', true);
                    break;
            }
        }

        // Show the help block if applicable.
        if ($selectedType == 'Localisation') {
            $this->parse('setting_group_localisation_block', 'setting_group_localisation');
        } elseif ($selectedType == 'Skin Config') {
            $this->parse('setting_group_skin_config_block', 'setting_group_skin_config');
        }
        $this->parse('setting_block', 'setting', true);
    }
}

/* Do not output save button if options are not editable */
if($editable) {
    $this->parse('setting_save_block','setting_save');
}
else {
    $this->setVar('setting_save_block','');
}
