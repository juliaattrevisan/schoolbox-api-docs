<?php
/**
 * Homepage configuration for course instance component
 *
 * @deprecated The course homepage setting isn't used anymore - each unit in a
 *             course has its own homepage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Config_CourseHomepage extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_NAME', $configValue);

        if (strlen($configValue)) {
            $page->setVar('COMPONENT_NAME', $configValue);
        }
    }
}
