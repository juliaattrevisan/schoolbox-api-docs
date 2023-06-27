<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Statistics\Visitation\Payload;

use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Statistics\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * Class Top10
 *
 * Gets the most recent 10 people who have visited the article
 */
class Top10
{
    /**
     * Top10 constructor.
     *
     * @param Mapper     $statisticsMapper
     * @param UserMapper $userMapper
     */
    public function __construct(private News\Statistics\Mapper $statisticsMapper, private \Schoolbox\UserMapper $userMapper)
    {
    }


    /**
     * Generate visitor payload data for $article
     *
     * @param Model $article
     * @return array
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws Exception
     */
    public function generate(News\Model $article): array
    {
        /* The user ids who visited at least once
         *
         * Users can have multiple records per article if their referrers are different so we can't
         * just take the first ten items in the findByColumn() stack
         */
        $statement = $this->statisticsMapper->getDatabase()->prepare("
            SELECT
                s1.user_id, s1.occurs_at,
                u.title, u.firstname, u.preferred_name, u.lastname,
                {$this->userMapper->getJoinColumnString('pronoun', 'op')},
                r.`name` AS `role`
            FROM {$this->statisticsMapper->getTable()} s1
            LEFT JOIN {$this->statisticsMapper->getTable()} s2
                ON s1.user_id = s2.user_id
                AND s1.article_id = :articleId
                AND s2.article_id = :articleId
                AND s1.occurs_at < s2.occurs_at
            LEFT JOIN `user` u ON s1.user_id = u.id
            LEFT JOIN `groups` r ON u.group_id = r.id
            LEFT JOIN {$this->userMapper->getJoinSql('pronoun', 'u', 'op')}
            WHERE s2.id IS NULL AND s1.article_id = :articleId
            ORDER BY s1.occurs_at DESC
            LIMIT 10
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $visitors = [];

        foreach ($statement as $stat) {
            $date = new \Alaress_Date_Format($stat['occurs_at'], \Alaress_Date_Format::DATABASE_FORMAT);

            /* Get access to formatName(), which only exists in a User instance
             */
            /** @var User $user **/
            $user = $this->userMapper->hydrate($stat);
            $user->setPronoun($this->userMapper->hydrateJoin($stat, 'pronoun', 'op'));

            $visitors[] = [
                'id' => $stat['user_id'],
                'occursAt' => [
                    'relative' => $date->getRelativeTime(),
                    'nice' => $date->getShort(),
                    'absolute' => $stat['occurs_at']
                ],
                'title' => $stat['title'],
                'firstname' => $stat['firstname'],
                'lastname' => $stat['lastname'],
                'fullname' => $user->formatName(),
                'role' => $stat['role']
            ];
        }

        return $visitors;
    }
}
