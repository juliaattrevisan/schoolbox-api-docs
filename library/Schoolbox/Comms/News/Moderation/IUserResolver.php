<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Database;
use DomainException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Comms;
use Schoolbox\Mapper;
use UnderflowException;

/**
 * Responsible for providing sets of users who can moderate an article
 */
interface IUserResolver
{

    /**
     * Returns the ids of users who are capable of moderating this article either because:
     * * they are superusers, or
     * * they are global news moderators, or
     * * they can moderate all of the article's audience segments
     *
     * @param Comms\News\Model $article
     * @return int[]
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnderflowException
     * @throws DomainException
     */
    public function findModeratorsForArticle(Comms\News\Model $article): array;


    /**
     * Returns the user.id of all superusers
     * @return int[]
     * @throws Database\Exception
     */
    public function getSuperuserIds(): array;


    /**
     * Returns the user ids of all news moderators (users with FULL access to NEWS service)
     *
     * @return int[]
     * @throws Database\Exception
     */
    public function getGlobalNewsModerators(): array;


    /**
     * Returns the user ids of users who can moderate this article because they can moderate
     * all audience segments of the article.
     *
     * @param Comms\News\Model $article
     * @return int[]
     */
    public function getAudienceModerators(Comms\News\Model $article): array;
}
