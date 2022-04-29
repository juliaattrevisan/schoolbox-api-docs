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
use Schoolbox\Forum\Post;
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
        $query = "SELECT `forumThread`.*, `forumPost`.*, `forumThread`.title AS `threadTitle`,
                            `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                            MATCH(`body`) AGAINST (:keywordBodySelect) AS score,
                            MATCH(`forumThread`.`title`) AGAINST (:keywordTitleSelect) AS `score_title`
                        FROM `forumPost`
                        JOIN `forumThread` ON `forumPost`.threadID = `forumThread`.threadID
                        JOIN `folder` ON `folder`.id = `forumThread`.categoryID AND `folder`.deleted_At IS NULL
                        LEFT JOIN `user` ON `forumPost`.userID = `user`.id
                        LEFT JOIN `perms` ON `forumThread`.categoryID = `perms`.cid
                            AND `perms`.gid = :roleId
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `forumThread`.categoryID = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = :userId
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND (
                                MATCH(`body`) AGAINST (:keywordBodyWhere) OR
                                MATCH(`forumThread`.`title`) AGAINST (:keywordTitleWhere)
                            )
                            AND `forumThread`.`deleted` = 'false'
                            AND `forumPost`.`deleted` = 'false'
                        ORDER BY :sortQuote DESC
                        LIMIT :offset, :limit;
            ";

        $stmt = $mapper->getDatabase()->prepare($query);
        $params = [
            'keywordBodySelect' => $sanitiser->getKeyword(),
            'keywordTitleSelect' => $sanitiser->getKeyword(),
            'keywordBodyWhere' => $sanitiser->getKeyword(),
            'keywordTitleWhere' => $sanitiser->getKeyword(),
            'roleId'            => $user->getRole()->getId(),
            'userId'            => $user->getId(),
            'sortQuote'         => $sanitiser->getSortBy(),
            'offset'                => $sanitiser->getOffset(),
            'limit'                => $sanitiser->getResultLimit()
        ];
        foreach ($params as $label => $value) {
            if ('offset' === $label || 'limit' === $label) {
                $stmt->bindValue($label, $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($label, $value);
            }
        }
        $stmt->execute();
        $collection = $mapper->createCollection();

        foreach ($this->buildSearchResult($stmt, $user, 'categoryID', $sanitiser->getOffset(), $sanitiser->getPageLimit()) as $row) {
            /** @var Post $post */
            $post = $mapper->findById($row['postID']);
            $collection->add($post);
        }
        return $mapper->hydrateCollection($collection);
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
