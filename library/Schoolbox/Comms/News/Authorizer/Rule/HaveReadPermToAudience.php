<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized("User cannot be null");
        }

        $article  = $this->findArticleInParams($params);
        $audience = $article->getAudience();
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
     */
    private function haveReadPermToSegmentAudience(User $user, Audience\Model $segment): bool
    {
        // Base Case
        if (Audience\Model::ENTITY_TYPE_COMPOSITE !== $segment->getEntityType()) {
            $strategy = $this->strategyFactory->make($segment->getEntityType());
            return $strategy->canViewEntityValue($user, $segment->getEntityValue());
        }

        // Recursive Case - segment must have 1+ children, and user must have READ+ perm to them all
        $childSegments = $segment->getChildren();
        if (!$childSegments || !$childSegments->count()) {
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
