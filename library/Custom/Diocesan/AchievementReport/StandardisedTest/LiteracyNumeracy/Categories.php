<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class Categories
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
class Categories
{

    /**
     * Generate a template of x categories
     *
     * @param array $placeholder
     * @param int   $numSeries
     * @return array
     */
    public function generate(array $placeholder, int $numSeries): array
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
}
