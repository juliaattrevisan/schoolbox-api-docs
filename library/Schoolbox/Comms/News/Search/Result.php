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
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
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

        /* The chain of readability
         */

        /* Superuser check
         */
        $isSuperuser = $user->isSuperuser();

        /* Are you a news moderator?
         */
        $accessList = $user->getAccessList();

        $hasNewsServicePermission = false;

        if (!empty($accessList)) {
            foreach ($accessList as $access) {
                $hasNewsServicePermission = $access->getService() === Access\UserAccess::SERVICE_NEWS;
            }
        }

        /* Are you the author?
         */
        $isAuthor = false;

        /* Is the article published or expired?
         */
        $isPublishedOrExpired = false;

        /* Is the article shareable?
         */
        $isShareable = false;

        /* Is $user in the audience of the article?
         */

        /* These are the folders to which the user has read+ access
         */
        $folderStrategy = $strategyFactory->makeSimpleStrategy('folder');
        $foldersWithRoleBasedPermissions = $folderStrategy->getSearchSql($user);

        foreach ($stmt as $row) {
        }
//
//        foreach ($this->buildSearchResult($stmt, $user, 'n.id', $sanitiser->getOffset(), $sanitiser->getLimit()) as $row) {
//            $article = $mapper->hydrate($row, 'n');
//
//            if ($article) {
//                $author = $mapper->hydrateJoin($row, 'author', 'a');
//
//                $article->setAuthor($author);
//
//                $collection->add($article);
//            }
//        }

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