<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Filter extends Component_Config_Abstract
{

    /**
     * Converts value from an array into a bitmask which represents the selected
     * fields.
     *
     * @param  array                        $value
     * @param  Component_ControllerAbstract $controller
     * @return int
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        $return = 0;
        $fieldList = $controller->getFilterFields();
        if (is_array($fieldList)) {
            foreach ($fieldList as $i => $field) {
                $add = !empty($value[$field]) ? 1 : 0;
                $return += $add * pow(2, $i);
            }
        }
        return $return;
    }

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        $filter = array();
        $configValue = (int)$configValue;

        for ($i = 0; $i < count($this->getFilterFields()); $i++) {
            $j = $configValue % 2;
            $filter[$this->getFilterFields()[$i]] = $j;
            $configValue = (int) $configValue / 2;
        }

        // has an internal block for the checkboxes
        $page->setBlock($keyblock, 'filters', 'filters_block');
        $page->setVar('filters_block', '');

        /* filter config */
        foreach ($filter as $option => $value) {
            $page->setVar(array(
                'COMPONENT_CONFIG_FILTER_VALUE'     => $option,
                'COMPONENT_CONFIG_FILTER_NAME'      => ucwords($option),
                'COMPONENT_CONFIG_FILTER_SELECTED'  => $value == 1 ? 'checked="checked"' : '',
            ));
            $page->parse('filters_block', 'filters', true);
        }
    }
}
