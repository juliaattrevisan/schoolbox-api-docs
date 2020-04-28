<?php
/**
 * @deprecated Twitter hashtags are no longer supported: use list slugs or collection IDs instead
 * @author     Matt Day <matt.day@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 */
class Component_Config_Keywords extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_KEYWORDS', $configValue);
    }
}
