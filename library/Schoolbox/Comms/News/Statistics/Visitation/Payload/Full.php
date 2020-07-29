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
         */
        $statement = $this->statisticsMapper->getDatabase()->prepare("
            SELECT
                s1.user_id, s1.occurs_at,
                u.title, u.firstname, u.lastname,
                r.`name` AS `role`
            FROM {$this->statisticsMapper->getTable()} s1
            LEFT JOIN {$this->statisticsMapper->getTable()} s2
                ON s1.user_id = s2.user_id
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
            $relative = (new \Alaress_Date_Format($stat['occurs_at'], \Alaress_Date_Format::DATABASE_FORMAT))->getRelativeTime();

            $visitors[$stat['user_id']] = [
                'id' => $stat['user_id'],
                'occursAt' => [
                    'relative' => $relative,
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

        /* This diff represents users that didn't visit the article, so we'll need to get
         * their details separately; they won't have any data from visiting. To minimise
         * hydration and mapper funsies, try to get a minimal set
         */
        $tempIds = array_diff($currentlyAudiencedIds, array_keys($visitors));
        $tempUsers = $this->userMapper->findByColumn('id', $tempIds);

        /* Dispense with all the object data we don't actually use and store it as a flat
         * hierarchy instead, so we can overlay it with visitor data
         */
        $users = [];

        foreach ($tempUsers as $tempUser) {
            $users[] = [
                'id' => $tempUser->getId(),
                'occursAt' => null,
                'title' => $tempUser->getTitle(),
                'firstname' => $tempUser->getFirstname(),
                'lastname' => $tempUser->getLastname(),
                'fullname' => $tempUser->getFullName(),
                'role' => $tempUser->getRole()->getName()
            ];
        }

        unset($tempUsers);

        /* Superimpose visitor data over user data
         * This will also insert visitors who aren't currently in the audience
         */
        foreach ($visitors as $id => $visitor) {
            $users[$id] = $visitor;
        }

        /* Sort by surname, then by firstname
         */
        uasort($visitors, static function ($a, $b) {
            if ($a['lastname'] === $b['lastname']) {
                return $a['firstname'] > $b['firstname'];
            }

            return ($a['lastname'] > $b['lastname']);
        });

        /* TODO Edge case
         *  Does the hyperdrive know about deleted users/do we care about deleted users?
         */

        return $users;
    }
}
