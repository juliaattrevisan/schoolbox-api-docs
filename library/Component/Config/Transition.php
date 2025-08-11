<?php

class Component_Config_Transition extends Component_Config_Abstract
{
    protected $options       = array('swipe', 'fade', 'flip', 'cube', 'creative');
    protected $showAllOption = true;

    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {

        // has an internal block for the select element
        $page->setBlock($keyblock, 'slideshowtransition', 'slideshowtransition_block');
        $page->setVar('slideshowtransition_block', '');

        foreach ($this->options as $o) {
            $page->setVar('COMPONENT_CONFIG_SLIDESHOWTRANSITION_NAME', ucfirst($o));
            $page->setVar('COMPONENT_CONFIG_SLIDESHOWTRANSITION_VALUE', $o);
            $page->setVar('COMPONENT_CONFIG_SLIDESHOWTRANSITION_SELECTED', $o == $configValue ? ' selected="selected"' : '');
            $page->parse('slideshowtransition_block', 'slideshowtransition', true);
        }
    }
}
