<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Validation;

use Alaress\Specification;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;

/**
 * Checks that a user is permitted to audience to the article's audience segments
 *
 * This should not be used in module auth checks in routes where the article's audience may be mutated, eg news.update
 * This ensures that user's who have lost permission to their article's audience can removed those segments
 */
class ValidUserCanAudienceTo extends Specification\Leaf
{

    /**
     * Note this is the effector, not the author.
     *
     * The implication being that a user could create an empty draft, then someone else could
     * come along and add audience segments to it which the author was unable to do themselves.
     *
     * @var User
     */
    private $effector;

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param User            $effector
     * @param StrategyFactory $strategyFactory
     */
    public function __construct(User $effector, StrategyFactory $strategyFactory)
    {
        $this->effector        = $effector;
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * @param News\Model $candidate
     * @return bool
     * @throws UnknownEntityTypeException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    public function isSatisfiedBy($candidate): bool
    {
        // Articles must have an audience
        if (!$candidate->getAudience()->count()) {
            return false;
        }

        // The effector must be able to audience to all root audience segments as these are aggregated using UNION
        foreach ($candidate->getAudience() as $rootSegment) {
            if (!$this->canAudienceTo($rootSegment)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Returns whether the article's audience is valid for the effecting user
     *
     * @param News\Audience\Model $segment
     * @return bool
     * @throws UnknownEntityTypeException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    private function canAudienceTo(News\Audience\Model $segment): bool
    {
        // Base Case
        if ($segment->isSimple()) {
            $strategy = $this->strategyFactory->makeStrategy($segment->getEntityType());
            if (!$strategy->canAudienceToEntityType($this->effector)) {
                return false;
            }
            return $strategy->canAudienceToEntityValue($this->effector, $segment->getEntityValue());
        }


        // Recursive case - Must be able to audience to ANY sub-segment as sub-segments are aggregated using INTERSECT
        if (!$segment->getChildren()->count()) {
            return false;
        }
        foreach ($segment->getChildren() as $childSegment) {
            if ($this->canAudienceTo($childSegment)) {
                return true;
            }
        }
        return false;
    }
}
