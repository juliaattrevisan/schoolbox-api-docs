<?php

/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Dsfg\AchievementReport\StandardisedTest;

abstract class Base extends \Custom\Dsfg\AchievementReport\Base
{

    /**
     * For highcharts, its default version of null is string 'null'
     */
    public const DEFAULT_NULL_VALUE = 'null';


    /**
     * Whether there was any data found
     *
     * @var bool
     */
    protected $hasData = false;


    /**
     * Find out if this student has data from any year
     *
     * @return bool
     */
    public function hasDataFromAnyYear(): bool
    {
        return $this->findData()->hasData();
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }
}
