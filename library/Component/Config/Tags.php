<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Config_Tags extends Component_Config_Abstract
{
    public function parse(
        SitePage $page,
        $key,
        $keyblock,
        $configValue,
        Component_ControllerAbstract $component = null
    ) {
        $page->setVar('COMPONENT_CONFIG_TAGS', $configValue);
    }
}
