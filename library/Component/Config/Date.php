<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
            $dateObject = Alaress_Date_Format::newLocalShort($value);
        } catch (Exception $e) {
            return '';
        }
        return $dateObject->getDatabaseDate();
    }

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if (!empty($configValue)) {
            $date = new Alaress_Date_Format(
                $configValue,
                Alaress_Date_Format::DATABASE_DATE_FORMAT
            );
            $configValue = $date->getLocalShort();
        }
        $page->setVar('COMPONENT_CONFIG_DATE', $configValue);
    }
}
