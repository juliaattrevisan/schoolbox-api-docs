<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Custom\Diocesan\AchievementReport\StandardisedTest;

/**
 * Class Base
 *
 * Used by Literacy/Numeracy section predominantly
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
abstract class Base extends \Custom\Diocesan\AchievementReport\Base
{

    /**
     * Whether there was any data found
     *
     * @var bool
     */
    protected $hasData = false;


    /**
     * Generate a template of x categories
     *
     * @param array $placeholder
     * @param int   $numSeries
     * @return array
     */
    protected function generateXCategories(array $placeholder, int $numSeries): array
    {
        $names = array_keys($placeholder);
        $template = [];

        foreach ($names as $category) {
            for ($i = 0; $i < $numSeries; $i++) {
                $template[] = $category;
            }
        }

        return $template;
    }


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


    /**
     * Find the AreaSeq sequence that we use to map red/blue/green data series
     *
     * @param array $placeholder
     * @return array
     */
    protected function generateAreaSeqMapping(array $placeholder): array
    {
        return array_keys(array_values($placeholder)[0]);
    }
}
