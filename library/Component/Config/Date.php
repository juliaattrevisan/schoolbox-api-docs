<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Date extends Component_Config_Abstract {

    /**
     * Coerce value to Y-m-d format before inserting into database.
     *
     * @param mixed                        $value
     * @param Component_ControllerAbstract $controller
     *
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller) {
        // Assume date was sent as d/m/Y, otherwise try to parse with strtotime
        if (empty($value)) {
            return '';
        }
        $dateObject = DateTime::createFromFormat('d/m/Y', $value);
        if (!($dateObject instanceof DateTime)) {
            try {
                $dateObject = new DateTime($value);
            }
            catch (Exception $e) {
                return '';
            }
        }
        return $dateObject->format('Y-m-d');
    }

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null) {
        if (!empty($configValue)) {
            $configValue = date('d/m/Y', strtotime($configValue));
        }
        $page->setVar('COMPONENT_CONFIG_DATE', $configValue);
    }

}
