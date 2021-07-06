<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\IB;

/**
 * Class Footer
 *
 * @package Custom\Dsfg\AchievementReport\IB
 */
class Footer extends Base
{

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        return \Config::get('dio_achievement_report_ib_footer');
    }
}
