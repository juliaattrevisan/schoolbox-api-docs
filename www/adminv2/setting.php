<?php

use Schoolbox\Config\ConfigMapper;

if (!$_SESSION['user']->isSuperuser()) {
	error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/setting.html');
$this->setTitle('Admin - Settings');

$this->setBlock(TEMPLATE_content, 'timepicker_js', 'timepicker_js_block');
$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');
$this->setBlock(TEMPLATE_content, 'setting', 'setting_block');
$this->setBlock('setting', 'setting_type_noneditable', 'setting_type_noneditable_block');
$this->setBlock(TEMPLATE_content, 'setting_group_localisation', 'setting_group_localisation_block');
$this->setBlock(TEMPLATE_content, 'setting_group_skin_config', 'setting_group_skin_config_block');
$this->setBlock('setting', 'setting_type_input', 'setting_type_input_block');
$this->setBlock('setting', 'setting_type_password', 'setting_type_password_block');
$this->setBlock('setting', 'setting_type_textarea', 'setting_type_textarea_block');
$this->setBlock('setting', 'setting_type_html', 'setting_type_html_block');
$this->setBlock('setting', 'setting_type_boolean', 'setting_type_boolean_block');
$this->setBlock('setting', 'setting_type_select', 'setting_type_select_block');
$this->setBlock('setting_type_select', 'setting_select', 'setting_select_block');
$this->setBlock('setting', 'setting_type_colour', 'setting_type_colour_block');
$this->setBlock('setting', 'setting_type_file', 'setting_type_file_block');
$this->setBlock('setting', 'setting_type_list', 'setting_type_list_block');
$this->setBlock('setting', 'setting_type_resource', 'setting_type_resource_block');
$this->setBlock('setting', 'setting_type_timepicker', 'setting_type_timepicker_block');
$this->setBlock(TEMPLATE_content, 'setting_save', 'setting_save_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'setting');

$this->setVar('setting_block', '');

/**
 * If no settings page has been set - set to Site
 */
if ($this->arg[0]) {
	$selectedType = urldecode($this->arg[0]);
} else {
	$selectedType = 'Site';
}

$optionList = ConfigMapper::getInstance()->findByTypeLabel($selectedType);

/**
 * 404 if there's nothing found with the settings page requested
 */
if (!$optionList->count()) {
    $this->pageNotFound();
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

$editable = false;
$hasTimepicker = false;

if(\is_iterable($optionList)) {
    foreach ($optionList as $option) {
        $output = array(
            'SETTING_TITLE' => Scrub::text($option->getTitle()),
            'SETTING_DESC'  => Scrub::text($option->getDescription()),
            'SETTING_NAME'  => Scrub::text($option->getName()),
            'SETTING_VALUE' => Scrub::text($option->getValue()),
            'setting_type_noneditable_block' => '',
            'setting_type_input_block'       => '',
            'setting_type_password_block'    => '',
            'setting_type_textarea_block'    => '',
            'setting_type_html_block'        => '',
            'setting_type_boolean_block'     => '',
            'setting_type_select_block'      => '',
            'setting_type_list_block'        => '',
            'setting_type_resource_block'    => '',
            'setting_type_colour_block'      => '',
            'setting_type_file_block'        => '',
            'setting_type_timepicker_block'        => '',
            'setting_group_localisation_block' => '',
            'setting_group_skin_config_block' => '',
        );

        $this->setVar($output);

        if ($option->getHidden()) {
            continue;
        }
        if (!$option->getEditable()) {
            $this->parse('setting_type_noneditable_block', 'setting_type_noneditable', true);
        }
        else {
            $editable = true;
            /* Decide whether this option should have
             * javascript validation enforced
             */
            if ($option->getRequired()) {
                $this->setVar(array(
                    'OPTION_REQUIRED' => 'required'
                ));
            }
            else {
                $this->setVar(array(
                    'OPTION_REQUIRED' => ''
                ));
            }

            switch ($option->getFieldId()) {
                case ConfigMapper::FIELD_INPUT:
                    $this->parse('setting_type_input_block', 'setting_type_input', true);
                    break;
                case ConfigMapper::FIELD_PASSWORD:
                    $this->parse('setting_type_password_block', 'setting_type_password', true);
                    break;

                case ConfigMapper::FIELD_TEXTAREA :
                    $this->parse('setting_type_textarea_block', 'setting_type_textarea', true);
                    break;

                case ConfigMapper::FIELD_HTML :
                    $this->parse('setting_type_html_block', 'setting_type_html', true);
                    break;

                case ConfigMapper::FIELD_RESOURCE:
                    $this->parse('setting_type_resource_block', 'setting_type_resource', true);
                    break;

                case ConfigMapper::FIELD_CHECKBOX:
                case ConfigMapper::FIELD_BOOLEAN :
                    if ($option->getValue()) {
                        $checked = 'checked="checked"';
                    }
                    else {
                        $checked = '';
                    }

                    $this->setVar('SETTING_CHECKED', $checked);
                    $this->parse('setting_type_input_block', 'setting_type_boolean', true);

                    break;

                case ConfigMapper::FIELD_SELECT:
                    $this->setVar('setting_select_block', '');
                    foreach (explode(':', $option->getOptions() ?? '') as $opt) {
                        $this->setVar('SELECT_VALUE', $opt);
                        $this->setVar('SELECT_OPTION', $opt);
                        $this->setVar('SETTING_OPTION_SELECTED', $option->getValue() == $opt ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case ConfigMapper::FIELD_SELECTKEY:
                    $this->setVar('setting_select_block', '');
                    foreach (explode(':', $option->getOptions() ?? '') as $opt) {
                        list($key, $value) = explode(';', $opt);
                        $this->setVar('SELECT_VALUE', $key);
                        $this->setVar('SELECT_OPTION', $value);
                        $this->setVar('SETTING_OPTION_SELECTED', $option->getValue() == $key ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case ConfigMapper::FIELD_SELECTLOOKUP:
                    $this->setVar('setting_select_block', '');
                    // options: expected to contain mapper
                    $lookupOptions  = unserialize($option->getOptions() ?? '');
                    $mapper         = call_user_func(array($lookupOptions['mapper'], 'getInstance'));
                    $getKeyMethod   = $lookupOptions['getKeyMethod']   ?: 'getId';
                    $getValueMethod = $lookupOptions['getValueMethod'] ?: 'getName';
                    $findMethod     = $lookupOptions['findMethod']     ?: 'findAll';
                    foreach (call_user_func(array($mapper, $findMethod)) as $opt) {
                        $key   = $opt->$getKeyMethod();
                        $value = $opt->$getValueMethod();
                        $this->setVar('SELECT_VALUE', $key);
                        $this->setVar('SELECT_OPTION', $value);
                        $this->setVar('SETTING_OPTION_SELECTED', $option->getValue() == $key ? 'selected="selected"' : '');
                        $this->parse('setting_select_block', 'setting_select', true);
                    }
                    $this->parse('setting_type_input_block', 'setting_type_select', true);
                    break;

                case ConfigMapper::FIELD_TIMEPICKER:
                    $hasTimepicker = true;
                    $this->parse('setting_type_timepicker_block', 'setting_type_timepicker');
                    break;

                case ConfigMapper::FIELD_BYTES:
                    $this->setVar('SETTING_VALUE', Alaress_Byte_Format::bytesToString($option->getValue()));
                    $this->parse('setting_type_input_block', 'setting_type_input', true);
                    break;

                case ConfigMapper::FIELD_COLOUR:
                    $this->parse('setting_type_colour_block', 'setting_type_colour', true);
                    break;

                case ConfigMapper::FIELD_FILE:
                    $mimeFilter = (strpos($option->getName(), '_logo') || strpos($option->getName(), '_bg')) ? 'image/*' : '*/*';

                    $zone = new \Schoolbox\Resource\Attachment\Zone(Scrub::text($option->getName()));
                    $zone->setThumbnailSize(64);
                    $zone->setAttachPrompt('Attach '. Scrub::text(strtolower($option->getTitle())));
                    $zone->setAttachmentsLabel('');
                    $zone->setFileVar(Scrub::text($option->getName()));
                    $zone->setMimeFilter($mimeFilter);
                    $zone->setModalTitle('Select '. Scrub::text(strtolower($option->getTitle())));
                    if($option->getValue()) {
                        $zone->attachFiles(array($option->getValue()));
                    }
                    $zone->renderIntoSitePage($this, 'ATTACH_ZONE');
                    $this->parse('setting_type_file_block', 'setting_type_file', true);
                    break;

                case 'fileMulti':
                    $mimeFilter = (strpos($option->getName(), '_logo') || strpos($option->getName(), '_bg')) ? 'image/*' : '*/*';

                    $zone = new \Schoolbox\Resource\Attachment\Zone(Scrub::text($option->getName()));
                    $zone->setThumbnailSize(64);
                    $zone->setAttachPrompt('Attach '. Scrub::text(strtolower($option->getTitle())));
                    $zone->setAttachmentsLabel('');
                    $zone->setMulti(true);
                    $zone->setFileVar(Scrub::text($option->getName())."[]");
                    $zone->setMimeFilter($mimeFilter);
                    $zone->setModalTitle('Select '. Scrub::text(strtolower($option->getTitle())));

                    $paths = explode(",", $option->getValue());

                    if (count($paths)) {
                        if ($zone->attachFiles($paths));
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

if ($hasTimepicker) {
    $this->parse('timepicker_js_block', 'timepicker_js');
} else {
    $this->setVar('timepicker_js_block', '');
}
