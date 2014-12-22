<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Timing extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'timings', 'timings_block');
        $page->setVar('timings_block', '');

        foreach (array(2, 5, 10, 15, 20, 30) as $i) {
            $page->setVar('COMPONENT_CONFIG_TIMING_NAME', $i);
            $page->setVar('COMPONENT_CONFIG_TIMING_VALUE', $i);
            $page->setVar('COMPONENT_CONFIG_TIMING_SELECTED', $i == $configValue ? ' selected="selected"' : '');
            $page->parse('timings_block', 'timings', true);
        }

    }
}
