<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Size extends Component_Config_Abstract
{

    /**
     * Returns the options to display in the dropdown
     *
     * @return int[]
     */
    protected function getOptions()
    {
        return [0, 1, 2, 3, 4, 5, 10, 15, 25, 30];
    }


    /**
     * Returns whether to include 'All' in the dropdown
     *
     * @return bool
     */
    protected function getShowAllOption()
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'sizes', 'sizes_block');
        $page->setVar('sizes_block', '');

        foreach ($this->getOptions() as $i) {
            // Don't show "All" option if it's disabled
            if ($i === 0 && !$this->getShowAllOption()) {
                continue;
            }
            $page->setVar('COMPONENT_CONFIG_SIZE_NAME', $i === 0 ? 'All' : $i);
            $page->setVar('COMPONENT_CONFIG_SIZE_VALUE', $i);
            $page->setVar('COMPONENT_CONFIG_SIZE_SELECTED', (string)$i === $configValue ? ' selected="selected"' : '');
            $page->parse('sizes_block', 'sizes', true);
        }

    }
}
