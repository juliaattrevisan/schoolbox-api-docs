<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\STAR;

use Schoolbox\User;

/**
 * Class ValidSTAR
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\STAR
 */
class ValidSTAR
{

    /**
     * Certain year levels always display the tab.
     * Other year levels display the tab only if there is data there
     *
     * @param User  $user
     * @return bool
     */
    public function isSatisifiedBy(User $user): bool
    {
        /* Only year 3 has this tab
         */
        return 3 === (int)$user->getYear();
    }
}
