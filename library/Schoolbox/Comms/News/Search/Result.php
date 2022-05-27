<?php

/**
 * Result
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Audience\Segment;
use Schoolbox\Comms\News\Model;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Search\MapperResult\Base;
use Schoolbox\Resource\Search\Sanitiser;
use Schoolbox\User;
use External_User;

class Result extends Base
{
    /** @var IConnector */
    private $database;

    public function __construct(IConnector $database, \Schoolbox\Permission\Access\Access $accessor = null)
    {
        parent::__construct($accessor);
        $this->database = $database;
    }


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
        $deps = $this->prepareSearchDependencies($articleResolver, $user, $mapper, $sanitiser);

        /* Hydrated articles end up in here
         */
        $collection = $mapper->createCollection();

        /* TODO: Implement shareable and throw it in as the last arg
         */
        $i = 0;

        foreach ($deps['resultSet'] as $row) {
            if (
                $this->isVisible(
                    $user,
                    $deps['hasServicePermission'],
                    (int)$row['n.id'],
                    $deps['articleIds'],
                    (int)$row['n.author_id'],
                    $deps['articlesWithRoleBasedPermissions'],
                    false
                )
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
                if ($collection->count() < $sanitiser->getPageLimit()) {
                    $article = $mapper->hydrate($row, 'n');

                    if ($article) {
                        $author = $mapper->hydrateJoin($row, 'author', 'a');

                        $article->setAuthor($author);

                        $collection->add($article);
                    }
                } else {
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
        $deps = $this->prepareSearchDependencies($articleResolver, $user, $mapper, $sanitiser);

        /* TODO: Implement shareable and throw it in as the last arg
         */
        $i = 0;

        foreach ($deps['resultSet'] as $row) {
            if (
                $this->isVisible(
                    $user,
                    $deps['hasServicePermission'],
                    (int)$row['n.id'],
                    $deps['articleIds'],
                    (int)$row['n.author_id'],
                    $deps['articlesWithRoleBasedPermissions'],
                    false
                )
            ) {
                $i++;
            }
        }

        return $i;
    }


    /**
     * I don't have access to a di to do this atm
     * Spawn from darkness for now
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param Mapper          $mapper
     * @param Sanitiser       $sanitiser
     * @return array
     * @throws Mapper\DefinitionException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     * @throws \Exception
     */
    private function prepareSearchDependencies(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser): array
    {
        // TODO SA-8354: inject this, or remove it completely if ElasticSearch happens
        $config = new \Schoolbox\Config\Loader();

        $strategyFactory = new Segment\StrategyFactory(
            Access\UserAccessMapper::getInstance(),
            $config->get(ConfigMapper::_STAFF_CAN_POST_NEWS),
            $config->get(ConfigMapper::_GLOBAL_MODERATION),
            $config->get(ConfigMapper::_AUDIENCE_TO_FOLDER_TEACHERS),
            $config->get(ConfigMapper::NAME_FIXTURES_ENABLED),
            Folder\Mapper::getInstance(),
            Access\RoleAccessMapper::getInstance(),
            Access\Access::getInstance(),
            new External_User(),
            $this->database,
            []
        );

        /* Is $user in the audience of the article?
         * (I assume that this should fetch only public articles?)
         */
        $articleIds = $articleResolver->findArticlesForUser($user, Model::STATE_PUBLIC);

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

        return [
            'articleIds' => $articleIds,
            'hasServicePermission' => $hasServicePermission,
            'articlesWithRoleBasedPermissions' => $articlesWithRoleBasedPermissions,
            'resultSet' => $resultSet
        ];
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
        $audienceMapper = \Schoolbox\Comms\News\Audience\Simple\Mapper::getInstance();

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

        foreach ($accessList as $access) {
            $hasNewsServicePermission = $access->getService() === Access\UserAccess::SERVICE_NEWS;
        }

        return $hasNewsServicePermission;
    }

    /**
     * Get a list of articles that match the keyword and are currently visible (published and not deleted or rejected)
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
                AND n.accepted_at IS NOT NULL
                AND n.publish_at < NOW()
                AND n.rejected_at IS NULL
                AND MATCH(n.title, n.blurb, n.body) AGAINST ({$sanitiser->getKeyword()})
            ORDER BY {$sanitiser->getSortByWithAlias('n')} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find out if an article is searchable for $user prior to hydrating it
     *
     * @param User   $user
     * @param bool   $hasServicePermission
     * @param int    $articleId
     * @param array  $articleIds
     * @param int    $authorId
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
        array $articlesWithRoleBasedPermissions,
        bool $isShareable
    ): bool {
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

        /* TODO: Implement shareable
         */
        if ($isShareable) {
            return true;
        }

        /* If one of your read+ folders is in the article's audience
         */
        if (\in_array($articleId, $articleIds, false)) {
            return true;
        }

        /* If the article has a folder in its audience and $user has read+ access
         * to that folder via role-based permissions
         */
        if (array_key_exists($articleId, $articlesWithRoleBasedPermissions)) {
            return true;
        }

        return false;
    }
}
