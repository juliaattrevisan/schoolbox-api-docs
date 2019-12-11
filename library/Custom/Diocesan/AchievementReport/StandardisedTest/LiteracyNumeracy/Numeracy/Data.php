<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Numeracy;

use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Base;
use Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Payload;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy\Numeracy
 */
class Data extends Base
{

    /**
     * Data source
     *
     * @var string
     */
    protected $code = 'LiteracyNumeracyY7_8_Mat';


    /**
     * Number of series to look for here
     * These denote the number of x-categories per data group
     */
    protected $series = [Payload::RED_SERIES, Payload::BLUE_SERIES, Payload::GREEN_SERIES];


    /**
     * @return mixed
     */
    public function findData()
    {
        return $this->findDataForSeries($this->code, $this->series);
    }
}
