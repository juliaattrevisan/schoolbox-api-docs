<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * Datetime configuration for components.
 */
class Component_Config_Datetime extends Component_Config_Abstract
{
    /**
     * Coerce value to local format before inserting into database.
     *
     * @param mixed                        $value
     * @param Component_ControllerAbstract $controller
     *
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        // Assume date was sent as a local date
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

    /**
     * @inheritdoc
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        if (!empty($configValue)) {
            try {
                // Use datetime format:
                // otherwise fall back to date and reset missing time fields
                $date = new Alaress_Date_Format(
                    $configValue,
                    [Alaress_Date_Format::DATABASE_FORMAT, Alaress_Date_Format::DATABASE_DATE_FORMAT . '|']
                );
                $configValue = $date->getLocal();
            } catch (Exception $e) {
                $configValue = '';
            }
        }
        $page->setVar('COMPONENT_CONFIG_DATETIME', $configValue);
    }
}
