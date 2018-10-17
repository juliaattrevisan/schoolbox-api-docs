<?php
/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Search;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Audience\Segment;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Search\MapperResult\Base;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;

class Result extends Base
{

    /**
     * Get all the search results
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser): Collection
    {

        /* I don't have access to a di to do this atm
         * Spawn from darkness for now
         */
        $config = new \Schoolbox\Config\Loader();

        $strategyFactory = new Segment\StrategyFactory(
            Access\UserAccessMapper::getInstance(),
            $config->get(ConfigMapper::_STAFF_CAN_POST_NEWS),
            $config->get(ConfigMapper::_GLOBAL_MODERATION),
            Folder\Mapper::getInstance(),
            Access\RoleAccessMapper::getInstance(),
            Access\Access::getInstance()
        );

        /* Is $user in the audience of the article?
         */
        $articleIds = $articleResolver->findArticlesForUser($user);

        /* Does $user have the News Moderator service permission?
         */
        $hasServicePermission = $this->hasServicePermission($user);

        /* These are the folders to which the user has read+ access
         */
        $articlesWithRoleBasedPermissions = $this->foldersWithReadAccess($user, $mapper, $strategyFactory);

        /* Generate result set that matched $keyword
         * then start filtering it based on access rules
         */
        $resultSet = $this->generateResultSet($mapper, $sanitiser);

        /* Hydrated articles end up in here
         */
        $collection = $mapper->createCollection();

        /* TODO: Implement shareable and throw it in as the last arg
         */
        $i = 0;

        foreach ($resultSet as $row) {
            if ($this->isVisible(
                $user,
                $hasServicePermission,
                (int)$row['n.id'],
                $articleIds,
                (int)$row['n.author_id'],
                $row['n.publish_at'],
                $row['n.expires_at'],
                $articlesWithRoleBasedPermissions,
                false)
            ) {

                /* Skip to the offset
                 */
                if ($i < $sanitiser->getOffset()) {
                    $i++;

                    continue;
                }

                /* Only obtain $limit items
                 * Once you've got the limit of items, break out
                 */
                if ($collection->count() <= $sanitiser->getLimit()) {
                    $article = $mapper->hydrate($row, 'n');

                    if ($article) {
                        $author = $mapper->hydrateJoin($row, 'author', 'a');

                        $article->setAuthor($author);

                        $collection->add($article);
                    }
                }
                else {
                    break;
                }
            }
        }

        return $collection;
    }


    /**
     * The total number of items found by the search
     *
     * @param ArticleResolver   $articleResolver
     * @param User              $user
     * @param \Schoolbox\Mapper $mapper
     * @param Sanitiser         $sanitiser
     * @return int
     * @throws Mapper\DefinitionException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser): int
    {
        /* I don't have access to a di to do this atm
         * Spawn from darkness for now
         */
        $config = new \Schoolbox\Config\Loader();

        $strategyFactory = new Segment\StrategyFactory(
            Access\UserAccessMapper::getInstance(),
            $config->get(ConfigMapper::_STAFF_CAN_POST_NEWS),
            $config->get(ConfigMapper::_GLOBAL_MODERATION),
            Folder\Mapper::getInstance(),
            Access\RoleAccessMapper::getInstance(),
            Access\Access::getInstance()
        );

        /* Is $user in the audience of the article?
         */
        $articleIds = $articleResolver->findArticlesForUser($user);

        /* Does $user have the News Moderator service permission?
         */
        $hasServicePermission = $this->hasServicePermission($user);

        /* These are the folders to which the user has read+ access
         */
        $articlesWithRoleBasedPermissions = $this->foldersWithReadAccess($user, $mapper, $strategyFactory);

        /* Generate result set that matched $keyword
         * then start filtering it based on access rules
         */
        $resultSet = $this->generateResultSet($mapper, $sanitiser);

        /* TODO: Implement shareable and throw it in as the last arg
         */
        $i = 0;

        foreach ($resultSet as $row) {
            if ($this->isVisible(
                $user,
                $hasServicePermission,
                (int)$row['n.id'],
                $articleIds,
                (int)$row['n.author_id'],
                $row['n.publish_at'],
                $row['n.expires_at'],
                $articlesWithRoleBasedPermissions,
                false)
            ) {
                $i++;
            }
        }

        return $i;
    }

    /**
     * $user has role-based read+ perms to the folders returned
     *
     * @param User                    $user
     * @param Mapper                  $mapper
     * @param Segment\StrategyFactory $strategyFactory
     * @return array
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     */
    private function foldersWithReadAccess(User $user, Mapper $mapper, Segment\StrategyFactory $strategyFactory): array
    {
        $foldersWithRoleBasedPermissions = [];
        $articlesWithRoleBasedPermissions = [];

        $statement = $mapper->getDatabase()->query($strategyFactory->makeSimpleStrategy('folder')->getSearchSql($user));

        /* Make a list of folders to which you have role-based read+ perms
         */
        foreach ($statement as $row) {
            $foldersWithRoleBasedPermissions[$row['cid']] = true;
        }

        /* Find a list of folders that are in an article's audience
         * See if there's an intersection; if there is, you can see the article
         */
        $audienceMapper = \Schoolbox\Comms\News\Audience\Mapper::getInstance();

        $result = $audienceMapper->getDatabase()->query("
            SELECT comms_news_article_id, entity_value
            FROM {$audienceMapper->getTable()}
            WHERE entity_type = 'folder'
        ");

        foreach ($result as $row) {
            if (array_key_exists($row['entity_value'], $foldersWithRoleBasedPermissions)) {
                $articlesWithRoleBasedPermissions[$row['comms_news_article_id']] = true;
            }
        }

        return $articlesWithRoleBasedPermissions;
    }

    /**
     * @param User $user
     * @return bool
     */
    private function hasServicePermission(User $user): bool
    {
        $accessList = $user->getAccessList();

        $hasNewsServicePermission = false;

        if (!empty($accessList)) {
            foreach ($accessList as $access) {
                $hasNewsServicePermission = $access->getService() === Access\UserAccess::SERVICE_NEWS;
            }
        }

        return $hasNewsServicePermission;
    }

    /**
     * Get a list of articles that match the keyword
     *
     * @param Mapper    $mapper
     * @param Sanitiser $sanitiser
     * @return array
     * @throws Mapper\DefinitionException
     */
    private function generateResultSet(Mapper $mapper, Sanitiser $sanitiser): array
    {
        $query = "
            SELECT
                {$mapper->getColumnString('n')},
                {$mapper->getJoinColumnString('author', 'a')},
                MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()}) AS `score`
            FROM
                `{$mapper->getTable()}` `n`
            INNER JOIN {$mapper->getJoinSql('author', 'n', 'a', true)}
            WHERE
                n.deleted_at IS NULL
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('n')} {$sanitiser->getSortDir()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $resultSet = [];

        foreach ($stmt as $row) {
            $resultSet[] = $row;
        }

        return $resultSet;
    }

    /**
     * Find out if an article is searchable for $user prior to hydrating it
     *
     * @param User   $user
     * @param bool   $hasServicePermission
     * @param int    $articleId
     * @param array  $articleIds
     * @param int    $authorId
     * @param string $publishAt
     * @param string $expiresAt
     * @param array  $articlesWithRoleBasedPermissions
     * @param bool   $isShareable
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function isVisible(
        User $user,
        bool $hasServicePermission,
        int $articleId,
        array $articleIds,
        int $authorId,
        string $publishAt,
        string $expiresAt,
        array $articlesWithRoleBasedPermissions,
        bool $isShareable
    ): bool
    {
        /* If $user is a superuser
         */
        if ($user->isSuperuser()) {
            return true;
        }

        /* If $user has News service permissions
         */
        if ($hasServicePermission) {
            return true;
        }

        /* If $user is the author
         */
        if ($user->getId() === $authorId) {
            return true;
        }

        $publishAt = new \Alaress_Date_Format($publishAt, \Alaress_Date_Format::DATABASE_FORMAT);
        $expiresAt = new \Alaress_Date_Format($expiresAt, \Alaress_Date_Format::DATABASE_FORMAT);
        $now = \Alaress_Date_Format::now();

        if ($publishAt->lte($now) || $expiresAt->lte($now)) {
            if ($isShareable) {
                return true;
            }

            if (\in_array($articleId, $articleIds, true)) {
                return true;
            }

            /* If the article has a folder in its audience and $user has read+ access
             * to that folder via role-based permissions
             */
            if (array_key_exists($articleId, $articlesWithRoleBasedPermissions)) {
                return true;
            }
        }

        return false;
    }
}