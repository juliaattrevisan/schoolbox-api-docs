<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Textbox extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $textboxMapper = new \Schoolbox\Resource\TextboxMapper();

        // has an internal block for the select element

        /* Select a textbox from some other homepage
		 */
        $altSource = $component instanceof Component_ControllerAbstract
            ? $component->getConfig('src')
            : null;
        if (is_numeric($altSource) && $altSource) {
            $allTextboxes = $textboxMapper->findByColumn('categoryID', $altSource);
        } else {
            $allTextboxes = $textboxMapper->findByColumn('categoryID', $this->getCategory()->getId());
        }

        $page->setBlock($keyblock, 'textbox_select', 'textbox_select_block');
        $page->setVar('textbox_select_block', '');

        if ($allTextboxes->count()) {
            foreach ($allTextboxes as $box) {
                $page->setVar(array(
                    'COMPONENT_CONFIG_BOXSELECT_NAME'       => Scrub::text($box['heading']),
                    'COMPONENT_CONFIG_BOXSELECT_VALUE' 	    => $box['textBoxID'],
                    'COMPONENT_CONFIG_BOXSELECT_SELECTED'   => $box['textBoxID'] == $configValue ? 'selected="selected"' : '',
                ));

                $page->parse("textbox_select_block", "textbox_select", true);
            }
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_BOXSELECT_NAME'       => Scrub::text(' (none) '),
                'COMPONENT_CONFIG_BOXSELECT_VALUE'      => '0',
                'COMPONENT_CONFIG_BOXSELECT_SELECTED'   => 'disabled="disabled"',
            ));

            $page->parse("textbox_select_block", "textbox_select", true);
        }
    }
}
