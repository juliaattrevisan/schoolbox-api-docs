<?php

/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_RandomStartingSlide extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => 'randomstartingslide',
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE' => '',
                'COMPONENT_CONFIG_RANDOM_STARTING_SLIDE_SELECTED' => ''
            ));
        }
    }
}
