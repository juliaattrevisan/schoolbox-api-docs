<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Filter extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {

        $filter = array();

        for($i = 0; $i < count($this->filterFields); $i++) {
            $j = $configValue % 2;
            $filter[$this->filterFields[$i]] = $j;
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
