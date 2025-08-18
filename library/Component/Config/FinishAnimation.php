<?php

class Component_Config_FinishAnimation extends Component_Config_Abstract
{
    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {
        if ($configValue == '1') {
            $page->setVar(array(
                'COMPONENT_CONFIG_FINISHANIMATION_SELECTED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'COMPONENT_CONFIG_FINISHANIMATION_SELECTED' => ''
            ));
        }
    }
}
