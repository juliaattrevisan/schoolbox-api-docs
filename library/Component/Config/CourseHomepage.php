<?php
/**
 * Homepage configuration for course instance component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Component_Config_CourseHomepage extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue) {
        $page->setVar('COMPONENT_CONFIG_NAME', $configValue);
        $page->setVar('COMPONENT_NAME', $configValue);
    }

}
