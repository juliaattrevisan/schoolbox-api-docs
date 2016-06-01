<?php
/**
 * Methods for translating date() format strings to formats for use by other
 * libraries.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Alaress_Date_Format_Converter
{
    /**
     * Convert a date() format string to a datepicker format string.
     *
     * @see http://api.jqueryui.com/datepicker/#utility-formatDate
     *
     * @param string $format
     *
     * @return string
     */
    public static function toDatepicker($format)
    {
        return strtr(
            $format,
            array(
                // day formats
                'j' => 'd',
                // datepicker doesn't support ordinal suffixes
                'S' => '',
                'd' => 'dd',
                'D' => 'D',
                'l' => 'DD',
                // month formats
                'n' => 'm',
                'm' => 'mm',
                'M' => 'M',
                'F' => 'MM',
                // year formats
                'y' => 'y',
                'Y' => 'yy',
            )
        );
    }

    /**
     * Convert a date() format string to a MomentJS format string.
     *
     * @see http://momentjs.com/docs/#/displaying/format/
     *
     * @param string $format
     *
     * @return string
     */
    public static function toMomentJs($format)
    {
        return strtr(
            $format,
            array(
                // day formats
                'j' => 'D',
                'S' => 'o',
                'd' => 'DD',
                'D' => 'ddd',
                'l' => 'dddd',
                // month formats
                'n' => 'M',
                'm' => 'MM',
                'M' => 'MMM',
                'F' => 'MMMM',
                // year formats
                'y' => 'YY',
                'Y' => 'YYYY',
            )
        );
    }
}
