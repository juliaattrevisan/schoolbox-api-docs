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

    public static function toDatepickerFormat(\Datetime $datetime)
    {
        return $datetime->format('d/m/Y h:ia');
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
}
