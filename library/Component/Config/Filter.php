<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Filter implements Component_Config_Interface {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        // DO NOT EDIT THIS ARRAY. MANIPULATE THE RESULTANT $filter VARIABLE IF YOU WANT TO PLAY WITH
        // THINGS LIKE ELEMENT DISPLAY ORDER
        $filterKeys = array('document', 'audio', 'video', 'image', 'other');
        $filter = array();

        for($i = 0; $i < count($filterKeys); $i++) {
            $j = $configValue % 2;
            $filter[$filterKeys[$i]] = $j;
            $configValue = (int) $configValue / 2;
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach ($filter as $option => $value) {
            $page->setVar(array(
                'COMPONENT_CONFIG_FILTER_VALUE' 	=> $option,
                'COMPONENT_CONFIG_FILTER_NAME' 		=> ucwords($option),
                'COMPONENT_CONFIG_FILTER_SELECTED' 	=> $value == 1 ? 'checked="checked"' : '',
            ));
            $page->parse('filters_block', 'filters', true);
        }
    }

}
