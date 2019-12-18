<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_merge;
use function array_unique;

/**
 * Responsible for finding a set of article's for a user's feed
 */
class UserFeed
{
    /** @var Mapper */
    private $newsMapper;

    /** @var ArticleResolver */
    private $articleResolver;

    /**
     * @param Mapper          $newsMapper
     * @param ArticleResolver $articleResolver
     */
    public function __construct(News\Mapper $newsMapper, ArticleResolver $articleResolver)
    {
        $this->newsMapper      = $newsMapper;
        $this->articleResolver = $articleResolver;
    }


    /**
     * Returns the set of published+expired article ids where user:
     * * authored article
     * * approved article
     * * is in article audience
     *
     * Optionally filters to a topic
     *
     * @param User        $user
     * @param string|null $topicSlug
     * @return array
     * @throws InvalidColumnException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function find(User $user, ?string $topicSlug): array
    {
        $relevantArticles = array_unique(
            array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user)
            )
        );

        return $this->newsMapper->findArticleIdsByFilters(
            $relevantArticles,
            $topicSlug,
            null,
            null,
            null,
            null,
            News\Model::STATE_PUBLIC,
            null
        );
    }
}
