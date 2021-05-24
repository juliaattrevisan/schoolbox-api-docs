<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_ImageRatio extends Component_Config_Abstract
{

    protected $options       = array('portrait','square','landscape','panorama','banner');
    protected $showAllOption = true;

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'imageratio', 'imageratio_block');
        $page->setVar('imageratio_block', '');

        foreach ($this->options as $o) {
            $page->setVar('COMPONENT_CONFIG_IMAGERATIO_NAME', ucfirst($o));
            $page->setVar('COMPONENT_CONFIG_IMAGERATIO_VALUE', $o);
            $page->setVar('COMPONENT_CONFIG_IMAGERATIO_SELECTED', $o == $configValue ? ' selected="selected"' : '');
            $page->parse('imageratio_block', 'imageratio', true);
        }
    }
}
