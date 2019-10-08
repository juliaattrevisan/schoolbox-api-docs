<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use UnexpectedValueException;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;

/**
 * Checks whether a user can moderate an article's *current* audience.
 *
 * WARNING. Note that if you use this rule in a route where the audience is being mutated, this rule will not inherently block the addition
 *          of audience segments where the user does not have moderate permission.
 */
class CanModerateAudience extends ArticleAttribute
{

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param StrategyFactory $strategyFactory
     */
    public function __construct(StrategyFactory $strategyFactory)
    {
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * @inheritdoc
     * @throws InsufficientHydrationException
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException If no article found in params
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized('$user must be provided');
        }

        $article = $this->findArticleInParams($params);

        // Articles that don't have audiences can only be moderated by Comms Officers and SUs
        if (null === $article->getAudience() || 0 === $article->getAudience()->count()) {
            throw new Unauthorized('Only CommsOfficers and SUs can moderate an article without an audience');
        }

        // Since root-level segments are a UNION, you must have moderation permission to ALL root segments.
        foreach ($article->getAudience() as $segment) {
            if (!$this->canModerate($user, $segment)) {
                throw new Unauthorized("User {$user->getId()} cannot moderate audience segment {$segment->getId()}");
            }
        }
    }


    /**
     * @param User           $user
     * @param Audience\Model $segment
     * @return bool
     * @throws InsufficientHydrationException
     * @throws UnknownEntityTypeException
     */
    private function canModerate(User $user, Audience\Model $segment): bool
    {
        // Base Case
        if ($segment->isSimple()) {
            return $this->strategyFactory->makeStrategy($segment->getEntityType())->canModerate($user, $segment->getEntityValue());
        }

        // Recursive case - Since composite children are an INTERSECT, the user can have moderation permission to ANY of the children.
        if (!$segment->getChildren()->count()) {
            return false;
        }
        foreach ($segment->getChildren() as $childSegment) {
            if ($this->canModerate($user, $childSegment)) {
                return true;
            }
        }
        return false;
    }
}
