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

        $filter = self::getFilter((int)$configValue, $this->getFilterFields());

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

    /**
     * Parse the bitmask which represents the selected fields and return
     * array map of field name and field value
     * @param int   $configValue
     * @param array $filterFields
     * @return array
     */
    public static function getFilter(int $configValue, array $filterFields): array
    {
        $filter = [];
        for ($i = 0; $i < count($filterFields); $i++) {
            $j                         = $configValue % 2;
            $filter[$filterFields[$i]] = $j;
            $configValue               = (int)$configValue / 2;
        }
        return $filter;
    }

    /**
     * Parse the bitmask which represents the selected fields and return
     * list of selected filter names
     * @param int   $configValue
     * @param array $filterFields
     * @return array
     */
    public static function getSelectedFilters(int $configValue, array $filterFields): array
    {
        $selectedFilters = [];
        $allFilter       = self::getFilter($configValue, $filterFields);
        foreach ($allFilter as $filterName => $filterValue) {
            if ($filterValue) {
                $selectedFilters[] = $filterName;
            }
        }
        return $selectedFilters;
    }
}
