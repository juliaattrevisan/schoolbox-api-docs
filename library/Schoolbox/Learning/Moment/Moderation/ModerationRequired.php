<?php

namespace Schoolbox\Learning\Moment\Moderation;

use Schoolbox\YearLevel;
use Schoolbox\YearLevel\Model;

class ModerationRequired
{
    /**
     * Using the global schoolbox config and a fully hydrated YearLevel instance,
     * decide whether Learning Moment Moderation is required
     *
     * Note that choosing "Not set" in user modify screen causes $yearLevel to be null,
     * at which point it falls back to the global admin config setting's...setting.
     *
     * @param bool $globalModeration
     * @param Model|null $yearLevel
     * @return bool
     */
    public function isSatisfiedBy(bool $globalModeration, ?YearLevel\Model $yearLevel): bool
    {
        /* If the $yearLevel is meaningful, use its setting
         */
        if (null !== $yearLevel) {
            return $yearLevel->getLearningMomentsModeration();
        }

        /* Otherwise fall back to whatever the global config says
         */
        return $globalModeration;
    }
}
