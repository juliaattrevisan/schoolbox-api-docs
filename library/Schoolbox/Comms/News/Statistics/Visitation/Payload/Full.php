<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Statistics\Visitation\Payload;

use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\UserMapper;

/**
 * Class Top10
 *
 * Gets the most recent 10 people who have visited the article
 */
class Full
{

    /**
     * @var News\Statistics\Mapper
     */
    private $statisticsMapper;


    /**
     * @var UserResolver
     */
    private $userResolver;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Top10 constructor.
     *
     * @param News\Statistics\Mapper $statisticsMapper
     * @param UserResolver           $userResolver
     * @param UserMapper             $userMapper
     */
    public function __construct(
        News\Statistics\Mapper $statisticsMapper,
        UserResolver $userResolver,
        UserMapper $userMapper
    ) {
        $this->statisticsMapper = $statisticsMapper;
        $this->userResolver = $userResolver;
        $this->userMapper = $userMapper;
    }


    /**
     * Generate visitor payload data for $article
     *
     * @param News\Model $article
     * @return array
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function generate(News\Model $article): array
    {
        /* The user data for those who visited at least once
         *
         * This sql query looks weird because it performs a groupwise maximum
         * without investing in a subselect
         * https://stackoverflow.com/questions/15211479/groupwise-maximum
         */
        $statement = $this->statisticsMapper->getDatabase()->prepare("
            SELECT
                s1.user_id, s1.occurs_at,
                u.title, u.firstname, u.lastname,
                r.`name` AS `role`
            FROM {$this->statisticsMapper->getTable()} s1
            LEFT JOIN {$this->statisticsMapper->getTable()} s2
                ON s1.user_id = s2.user_id
                AND s1.article_id = :articleId
                AND s2.article_id = :articleId
                AND s1.occurs_at < s2.occurs_at
            LEFT JOIN `user` u ON s1.user_id = u.id
            LEFT JOIN `groups` r ON u.group_id = r.id
            WHERE s2.id IS NULL AND s1.article_id = :articleId
            ORDER BY s1.occurs_at DESC
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $visitors = [];

        foreach ($statement as $stat) {
            $date = new \Alaress_Date_Format($stat['occurs_at'], \Alaress_Date_Format::DATABASE_FORMAT);

            $visitors[$stat['user_id']] = [
                'id' => $stat['user_id'],
                'occursAt' => [
                    'relative' => $date->getRelativeTime(),
                    'nice' => $date->getShort(),
                    'absolute' => $stat['occurs_at']
                ],
                'title' => $stat['title'],
                'firstname' => $stat['firstname'],
                'lastname' => $stat['lastname'],
                'fullname' => trim("{$stat['title']} {$stat['firstname']} {$stat['lastname']}"),
                'role' => $stat['role']
            ];
        }

        /* The user ids who were audienced
         */
        $currentlyAudiencedIds = $this->userResolver->findUsersForArticle($article);

        /* TODO Edge case
         *  Does the hyperdrive know about deleted users/do we care about deleted users?
         */

        /* This diff represents users that didn't visit the article, so we'll need to get
         * their details separately; they won't have any data from visiting. To minimise
         * hydration and mapper funsies, try to get a minimal set
         *
         * Calculate diff,
         * Cast everything to int to prevent weirdness,
         * Implode into a sql-compatible piece,
         * Shank it in
         */
        $tempIds = implode(',', array_map('\intval', array_diff($currentlyAudiencedIds, array_keys($visitors))));
        if (empty($tempIds)) {
            $tempUsers = [];
        } else {
            $tempUsers = $this->userMapper->getDatabase()->prepare("
                SELECT
                    u.id, u.title, u.firstname, u.lastname,
                    r.`name` AS `role`
                FROM {$this->userMapper->getTable()} u
                LEFT JOIN `groups` r ON u.group_id = r.id
                WHERE u.id IN ({$tempIds})
            ");


            $tempUsers->execute();
        }

        /* Dispense with all the object data we don't actually use and store it as a flat
         * hierarchy instead, so we can overlay it with visitor data
         */
        $users = [];

        foreach ($tempUsers as $tempUser) {
            $users[$tempUser['id']] = [
                'id' => $tempUser['id'],
                'occursAt' => null,
                'title' => $tempUser['title'],
                'firstname' => $tempUser['firstname'],
                'lastname' => $tempUser['lastname'],
                'fullname' => trim("{$tempUser['title']} {$tempUser['firstname']} {$tempUser['lastname']}"),
                'role' => $tempUser['role']
            ];
        }

        /* Superimpose visitor data over user data
         * This will also insert visitors who aren't currently in the audience
         */
        foreach ($visitors as $id => $visitor) {
            $users[$id] = $visitor;
        }

        /* Sort by surname, then by firstname
         */
        uasort($users, static function ($a, $b) {
            if ($a['lastname'] === $b['lastname']) {
                return $a['firstname'] > $b['firstname'];
            }

            return ($a['lastname'] > $b['lastname']);
        });

        return array_values($users);
    }
}
