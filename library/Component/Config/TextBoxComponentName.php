<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_TextBoxComponentName extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('TEXT_BOX_COMPONENT_NAME', $configValue);
    }
}
