<?php

/**
 * Result
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Forum\Search;

use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Forum\Thread;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Mapper;
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
     */
    protected function executeSearch(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        $query = "
            SELECT
                {$mapper->getColumnString()},
                {$mapper->getJoinMapper('post')->getColumnString()},
                {$mapper->getJoinMapper('category')->getColumnString()},
                {$mapper->getJoinMapper('owner')->getColumnString()},
                MATCH(forumPost.body) AGAINST ({$sanitiser->getKeyword()}) AS `score`,
                MATCH(forumThread.title) AGAINST ({$sanitiser->getKeyword()}) AS `score_title`
            FROM
                `{$mapper->getTable()}`
                LEFT JOIN {$mapper->getJoinSql('owner', '', '', true)}
                INNER JOIN {$mapper->getJoinSql('category', '', '')}
                INNER JOIN {$mapper->getJoinSql('post', '', '')}
            WHERE
                forumPost.deleted = 'false'
            AND
                forumThread.deleted = 'false'
                AND (
                     MATCH(forumPost.body) AGAINST ({$sanitiser->getKeyword()}) OR
                     MATCH(forumThread.title) AGAINST ({$sanitiser->getKeyword()})
                    )
            ORDER BY {$sanitiser->getSortBy()} {$sanitiser->getSortDir()}
            LIMIT 0,{$sanitiser->getResultLimit()}
        ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $stmt->execute();
        $post_mapper = $mapper->getJoinMapper('post');
        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'categoryID', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            /** @var Thread $thread */
            $thread = $mapper->hydrate($row);
            /** @var User $owner */
            $owner = $mapper->hydrateJoin($row, 'owner');
            /** @var FolderModel $folder */
            $folder = $mapper->hydrateJoin($row, 'category');
            $thread->setPosts($post_mapper->findByColumn('threadID', $thread->getId()));
            $thread->setUser($owner)->setCategory($folder);

            $collection->add($thread);
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
     */
    protected function executeSearchCount(ArticleResolver $articleResolver, User $user, Mapper $mapper, Sanitiser $sanitiser)
    {
        $collection = $this->executeSearch($articleResolver, $user, $mapper, $sanitiser);

        return $collection->count();
    }
}
