<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Sortdir extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'sorting_dir', 'sorting_dir_block');
        $page->setVar('sorting_dir_block', '');

        $sortable = array(
            'asc' => 'Ascending',
            'desc' => 'Descending'
        );

        foreach ($sortable as $k => $opt) {
            $page->setVar('COMPONENT_CONFIG_SORTDIR_NAME', $opt);
            $page->setVar('COMPONENT_CONFIG_SORTDIR_VALUE', $k);
            $page->setVar('COMPONENT_CONFIG_SORTDIR_SELECTED', $k == $configValue ? ' selected="selected"' : '');
            $page->parse('sorting_dir_block', 'sorting_dir', true);
        }
    }
}
