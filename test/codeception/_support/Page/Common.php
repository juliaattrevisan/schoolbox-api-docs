<?php

/**
 * Created by PhpStorm.
 * User: tomoko.tanaka
 * Date: 27/06/18
 * Time: 11:44 AM
 */

namespace Page;

class Common
{
    public const USER_ZANE = 'Mr Zane Smith';
    public const USER_ID_ZANE = 151;
    public const USER_RACHEL = 'Rachel Rhodes';
    public const USER_ID_RACHEL = 229;
    public const USER_DAVID = 'David Rhodes';
    public const USER_ID_DAVID = 230;

    public const CLASS_CHEMISTRY_1_3 = 'Chemistry 1 3';
    public const CLASS_ID_CHEMISTRY_1_3 = 176;

    public const SELECTOR_JELLY_TOT = '#content a.icon-more';

    /**
     * Content element selector. This is where most of Schoolbox's dynamic
     * content goes.
     */
    public const ELEMENT_CONTENT = '#content';

    /**
     * Footer selector. This is where the copyright, policy, version number and
     * support link go.
     */
    public const ELEMENT_FOOTER = '#footer';

    /**
     * Top menu selector. This is where the configurable top menu items go.
     */
    public const ELEMENT_TOP_MENU = '#top-menu';

    /**
     * Search field selector; used as a text input for site searches.
     */
    public const ELEMENT_SEARCH = '#search-field';

    /**
     * Side menu selector; this is where the configurable side menu items go,
     * as well as the current user's groups, classes and links.
     *
     * (for some reason, this has no ID?)
     */
    public const ELEMENT_SIDEBAR = '.left-off-canvas-menu';

    /**
     * Notification center selector; this is where new notifications go.
     */
    public const ELEMENT_NOTIFICATIONS = '#msgs';

    /**
     * Date format as used in form inputs.
     *
     * This should match `config.date_local`; you can set it if you want.
     */
    public const FORMAT_INPUT_DATE = 'd/m/Y';

    /**
     * Time format as used in form inputs.
     *
     * This should match `config.date_time`; you can set it if you want.
     */
    public const FORMAT_INPUT_TIME = 'g:ia';

    /**
     * Date+time format as used in form inputs.
     */
    public const FORMAT_INPUT_DATETIME = self::FORMAT_INPUT_DATE . ' ' . self::FORMAT_INPUT_TIME;


    public static function toDatepickerFormat(\Datetime $datetime)
    {
        return $datetime->format(self::FORMAT_INPUT_DATETIME);
    }

    public static function gentext(int $length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz.012345678!@#$%*&^()-+';

        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($length > 8 && rand(1, 7) === 5) {
                $randomString .= ' ';
                $i++;
            }
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public static function surnameFirst($fullname)
    {
        $name = str_replace(['Mr ', 'Mrs '], '', $fullname);
        $parts = explode(' ', $name);
        return $parts[1] . ', ' . $parts[0];
    }
}
