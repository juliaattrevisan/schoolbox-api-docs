<?php

namespace Schoolbox\Learning\Moment\Evidence\Moderation;

use Schoolbox\YearLevel;
use Schoolbox\YearLevel\Model;

class ModerationEnabled
{
    /**
     * Using the global schoolbox config and a fully hydrated YearLevel instance,
     * decide whether Learning Moment Moderation is enabled
     *
     * @param bool $globalModeration
     * @param Model $yearLevel
     * @return bool
     */
    public function isSatisfiedBy(bool $globalModeration, YearLevel\Model $yearLevel): bool
    {
        return $globalModeration || $yearLevel->getLearningMomentsModeration();
    }
}