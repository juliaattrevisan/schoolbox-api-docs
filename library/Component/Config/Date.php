<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Config_Date extends Component_Config_Abstract
{

    /**
     * Coerce value to Y-m-d format before inserting into database.
     *
     * @param mixed                        $value
     * @param Component_ControllerAbstract $controller
     *
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        // Assume date was sent as a local short date
        if (empty($value)) {
            return '';
        }
        try {
            $dateObject = Alaress_Date_Format::newLocal($value);
        } catch (Exception $e) {
            return '';
        }
        return $dateObject->getDatabase();
    }

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if (!empty($configValue)) {
            $date = new Alaress_Date_Format(
                $configValue,
                Alaress_Date_Format::DATABASE_FORMAT
            );
            $configValue = $date->getLocal();
        }
        $page->setVar('COMPONENT_CONFIG_DATE', $configValue);
    }
}
