<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_merge;
use function array_unique;

/**
 * Responsible for consistently finding a set of articles for a user's feed
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
     * Returns the set of article ids where user either:
     * * authored article
     * * approved article
     * * is in article audience
     *
     * Optionally filters to a topic
     *
     * @param User        $user
     * @return array
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function find(User $user): array
    {
        return array_unique(
            array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user)
            )
        );
    }
}
