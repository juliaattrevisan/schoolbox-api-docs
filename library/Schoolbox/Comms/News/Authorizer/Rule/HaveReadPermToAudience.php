<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\User;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Determines whether a user has READ+ perm to a new article's audience.
 *
 * eg, Consider an article audienced to Folder:X
 *     A user may have READ+ role perm to Folder:X, but not be a member.
 *     This user should be able to view the article by virtue of having READ+ perm to the article's audience
 */
class HaveReadPermToAudience extends ArticleAttribute
{

    /**
     * @var StrategyFactory
     */
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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     * @throws \DomainException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized("User cannot be null");
        }

        $article  = $this->findArticleInParams($params);
        $audience = $article->getAudience();

        /* Needs audience hydration into $article
         * Otherwise it throws the Unauthorized exception
         */
        $audienceSegments = Audience\Mapper::getInstance()->findForArticles([$article->getId()]);
        $article->setAudience($audienceSegments);

        if (!$audience || !$audience->count()) {
            throw new Unauthorized('Article has no audience segments');
        }
        foreach ($audience as $rootSegment) {
            // You can view article if you can view any root segment
            if ($this->haveReadPermToSegmentAudience($user, $rootSegment)) {
                return;
            }
        }
        throw new Unauthorized('User does not have READ+ perm to any article audience segment');
    }


    /**
     * @param User           $user
     * @param Audience\Model $segment
     * @return bool
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     */
    private function haveReadPermToSegmentAudience(User $user, Audience\Model $segment): bool
    {
        // Base Case
        if ($segment->isSimple()) {
            $strategy = $this->strategyFactory->makeStrategy($segment->getEntityType());
            return $strategy->canViewArticleAudiencedToEntityValue($user, $segment->getEntityValue(), $segment->getRelationship());
        }

        // Recursive Case - segment must have 1+ children, and user must have READ+ perm to them all
        $childSegments = $segment->getChildren();
        if (!$childSegments->count()) {
            return false;
        }
        foreach ($segment->getChildren() as $childSegment) {
            if (!$this->haveReadPermToSegmentAudience($user, $childSegment)) {
                return false;
            }
        }
        return true;
    }
}
