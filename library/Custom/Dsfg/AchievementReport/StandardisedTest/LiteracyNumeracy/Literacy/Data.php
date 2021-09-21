<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy\Literacy;

use Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy\Base;
use Custom\Dsfg\AchievementReport\StandardisedTest\LiteracyNumeracy\Payload;

class Data extends Base
{

    /**
     * Data source
     *
     * @var string
     */
    protected $code = 'LiteracyNumeracyY7_8_Lit';


    /**
     * Number of series to look for here
     * These denote the number of x-categories per data group
     */
    protected $series = [Payload::RED_SERIES, Payload::BLUE_SERIES];


    /**
     * @return mixed
     */
    public function findData()
    {
        return $this->findDataForSeries($this->code, $this->series);
    }
}
