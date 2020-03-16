<?php
/**
 * Report Factory for generating concrete instances of reports.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class Report_Factory {

    /**
     * Allowed report types
     */
    public const ALLOWED_REPORTS = ['group', 'activity', 'login'];

    /**
     * Special char used in search vars to separate key-value pairs
     */
    public const PARAM_SEPARATOR = ';';

    // provide optional type safety
    private static $typesafe = false;


    /**
     * Determine if $reportType is accepted
     *
     * @param string|null $reportType
     * @return bool
     */
    public static function isValidReport(?string $reportType): bool
    {
        return \in_array(strtolower($reportType), self::ALLOWED_REPORTS, true);
    }


    /**
     * @static
     * @param $reportType
     * @param $page
     * @return Report_IReport
     */
    public static function build($reportType, $page): Report_IReport
    {

        /* Invalid reports don't do anything
         */
        if (self::$typesafe && !self::isValidReport($reportType)) {
            // don't return anything
            return null;
        }

        // get the class to be implemented
        $class = "Report_" . ucwords(strtolower($reportType));

        if (!class_exists($class)) {
            // not found
            return null;
        }

        // instantiate
        return new $class($page);
    }
}
