<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Validation;

use Alaress\Specification;
use Schoolbox\Comms\News;
use Schoolbox\User;

/**
 * Checks that a user is permitted to audience to the articles audience segments
 */
class ValidAudience extends Specification\Leaf
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

    /** @var News\Audience\Segment\StrategyFactory */
    private $strategyFactory;


    /**
     * @param User $effector
     */
    public function __construct(User $effector, News\Audience\Segment\StrategyFactory $strategyFactory)
    {
        $this->effector = $effector;
        $this->strategyFactory = $strategyFactory
    }


    /**
     * Returns whether the candidate audience specification satisfies this specification
     *
     * @param mixed|News\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        // Articles must have an audience
        if(!$candidate->getAudience() || !$candidate->getAudience()->count()){
            return false;
        }

        // The effector must be able to audience to all audience segments
        foreach($candidate->getAudience() as $rootSegment){
            if(!$this->canAudienceTo($rootSegment)){
                return false;
            }
        }
        return true;
    }

    private function canAudienceTo(News\Audience\Model $segment): bool
    {
        // Base Case
        if(News\Audience\Model::ENTITY_TYPE_COMPOSITE === $segment->getEntityType()){
            $spec = $this->strategyFactory->makeStrategy($segment->getEntityType());
            if($spec->canBeTargetedByUser()){
                return false;
            }
            if($spec->canBeTargetedByUser())


        }


        // Recursive case - Must be able to audience to ANY sub-segment
        foreach($segment->getChildren() as $childSegment){
            if($this->canAudienceTo($childSegment)){
                return true;
            }
        }
        return false;
    }
}
