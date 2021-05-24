<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Sort extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'sorting', 'sorting_block');
        $page->setVar('sorting_block', '');

        foreach ($this->getSortFields() as $opt) {
            $page->setVar('COMPONENT_CONFIG_SORT_NAME', ucfirst($opt));
            $page->setVar('COMPONENT_CONFIG_SORT_VALUE', $opt);
            $page->setVar('COMPONENT_CONFIG_SORT_SELECTED', strtolower($opt) == strtolower($configValue) ? ' selected="selected"' : '');
            $page->parse('sorting_block', 'sorting', true);
        }
    }
}
