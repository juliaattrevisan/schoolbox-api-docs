<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class AreaSeqMapping
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
class AreaSeqMapping
{

    /**
     * Find the AreaSeq sequence that we use to map red/blue/green data series
     *
     * @param array $placeholder
     * @return array
     */
    public function generate(array $placeholder): array
    {
        return array_keys(array_values($placeholder)[0]);
    }
}
