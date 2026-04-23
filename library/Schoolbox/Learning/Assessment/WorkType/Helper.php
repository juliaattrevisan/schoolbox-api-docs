<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\WorkType;

class Helper
{
    /**
     * @param bool $isVisibleToParents
     * @param bool $isVisibleToLearners
     * @return string
     */
    public static function getVisibilityFromBooleans(bool $isVisibleToParents, bool $isVisibleToLearners): string
    {
        return match ([$isVisibleToParents, $isVisibleToLearners]) {
            [false, false] => Mapper::VISIBILITY_STAFF_INSTRUCTORS,
            [false, true ] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS,
            [true , false] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_PARENTS,
            [true , true ] => Mapper::VISIBILITY_STAFF_INSTRUCTORS_LEARNERS_PARENTS,
        };
    }
}
