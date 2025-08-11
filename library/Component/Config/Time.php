<?php

/**
 * @deprecated No components use this configuration anymore
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Time extends Component_Config_Abstract
{
    /**
     * Coerce value to H:i:s format before inserting into database
     *
     * @param mixed                        $value
     * @param Component_ControllerAbstract $controller
     *
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        // Assume time was sent as H:i (which is parseable by strtotime)
        if (empty($value)) {
            return '';
        }
        try {
            $dateObject = new DateTime($value);
        } catch (Exception $e) {
            return '';
        }
        return $dateObject->format('H:i:s');
    }

    public function parse(SitePage $page, $key, $keyblock, $configValue, ?Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_TIME', $configValue);
    }
}
