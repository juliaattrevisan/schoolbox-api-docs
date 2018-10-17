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
                AND n.publish_at <= NOW()
                AND n.accepted_at <= NOW()
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('n')} {$sanitiser->getSortDir()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        $collection = $mapper->createCollection();

        $config = new \Schoolbox\Config\Loader();

        /* I don't have access to a SitePage here atm
         * so I guess imma spawn this from darkness for now
         */
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
        $foldersWithRoleBasedPermissions = $this->foldersWithReadAccess($user, $mapper, $strategyFactory);

        /* TODO: Implement shareable and throw it in as the last arg
         */
        foreach ($stmt as $row) {
            if ($this->isVisible(
                $user,
                $hasServicePermission,
                (int)$row['n.id'],
                $articleIds,
                (int)$row['n.author_id'],
                $row['n.publish_at'],
                $row['n.expires_at'],
                $foldersWithRoleBasedPermissions,
                false)
            ) {
                $article = $mapper->hydrate($row, 'n');

                if ($article) {
                    $author = $mapper->hydrateJoin($row, 'author', 'a');

                    $article->setAuthor($author);

                    $collection->add($article);
                }
            }
        }

        return $collection;
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

        $folderStrategy = $strategyFactory->makeSimpleStrategy('folder');

        $statement = $mapper->getDatabase()->query($folderStrategy->getSearchSql($user));

        foreach ($statement as $row) {
            $foldersWithRoleBasedPermissions[] = $row['cid'];
        }

        return $foldersWithRoleBasedPermissions;
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
     * Find out if an article is searchable for $user prior to hydrating it
     *
     * @param User   $user
     * @param bool   $hasServicePermission
     * @param int    $articleId
     * @param array  $articleIds
     * @param int    $authorId
     * @param string $publishAt
     * @param string $expiresAt
     * @param array  $foldersWithRoleBasedPermissions
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
        array $foldersWithRoleBasedPermissions,
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
        }

        return false;
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
     */
    protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser): int
    {
        $query = "
            SELECT
                n.id
            FROM
                `{$mapper->getTable()}` `n`
            INNER JOIN {$mapper->getJoinSql('author', 'n', 'a', true)}
            WHERE
                n.deleted_at IS NULL
                AND n.publish_at <= NOW()
                AND n.accepted_at <= NOW()
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $this->buildSearchCount($stmt, $user, 'id');
    }
}