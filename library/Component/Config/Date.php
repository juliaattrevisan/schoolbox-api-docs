<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Date extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue, $allConfigs = array()) {

        // Coerce the config value to d/m/Y format.
        $dateObject = DateTime::createFromFormat('d/m/Y', $configValue);
        if (!($dateObject instanceof DateTime)) {
            try {
                $dateObject  = new DateTime($configValue);
                $configValue = $dateObject->format('d/m/Y');
            }
            catch (Exception $e) {}
        }

        $page->setVar('COMPONENT_CONFIG_DATE', $configValue);
    }

}
