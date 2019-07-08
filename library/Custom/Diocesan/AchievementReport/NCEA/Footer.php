<?php
/**
 * Footer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Diocesan\AchievementReport\NCEA;


class Footer extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        return \Cfg::get('dio_achievement_report_ncea_footer');
    }
}
