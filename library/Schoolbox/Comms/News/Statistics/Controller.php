<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Comms\News\Statistics
 */
class Controller extends Base
{

    /**
     * @var IConnector
     */
    private $database;


    /**
     * @var Mapper
     */
    private $statisticsMapper;


    /**
     * @var Cumulative\Mapper
     */
    private $statisticsCumulativeMapper;


    /**
     * @var UserResolver
     */
    private $userResolver;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Controller constructor.
     *
     * @param IConnector        $databaseInternal
     * @param Mapper            $statisticsMapper
     * @param Cumulative\Mapper $statisticsCumulativeMapper
     * @param UserResolver      $userResolver
     * @param UserMapper        $userMapper
     */
    public function __construct(
        IConnector $databaseInternal,
        Mapper $statisticsMapper,
        Cumulative\Mapper $statisticsCumulativeMapper,
        UserResolver $userResolver,
        UserMapper $userMapper
    ) {
        $this->database = $databaseInternal;
        $this->statisticsMapper = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->userResolver = $userResolver;
        $this->userMapper = $userMapper;
    }


    /**
     * Statistical overview for $article
     *
     * @api GET /news/statistics/{articleId}
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getOverview(News\Model $article): void
    {
        $interactions = new Interactions($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,

            /* Length of period to record granular data
             */
            'period' => Interactions::PERIOD_LENGTH,

            /* Overall Cumulative summary
             */
            'cumulative' => $interactions->fetchCumulative($article),

            /* Overall Referrer Demographics
             */
             'referrerDemographic' => $interactions->fetchReferrerDemographic($article),

            /* Overall RoleType Demographics
             * Temporarily disabled
             */
            // 'roleTypeDemographic' => $interactions->fetchRoleTypeDemographic($article),

            /* Scale selector
             */
            'scaleOptions' => News\Statistics\Scale\Factory::SCALE_OPTIONS,
            'scale' => News\Statistics\Scale\Factory::assertScale($this->getRequest()->query['scale']),

            /* Historical Dailies
             * Temporarily disabled
             */
            // 'historic' => $interactions->fetchHistoric($article),
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }


    /**
     * Get the json payload to populate "Opens vs Time" graph so that
     * when you change scale, only the graph updates, instead of the whole page kek
     *
     * @api GET /news/statistics/{articleId}/interactions
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     */
    public function getInteractionsVsTime(News\Model $article): void
    {
        $interactions = new Interactions($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $scale = News\Statistics\Scale\Factory::assertScale($this->getRequest()->query['scale']);

        $output = [
            'interactions' => $interactions->fetchOpensVsTime($article, $scale),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }


    /**
     * Get the most recent 10 visitors to $article
     *
     * @param News\Model $article
     * @throws \PDOException
     */
    public function getTop10(News\Model $article): void
    {

        /* The user ids who visited at least once
         *
         * Users can have multiple records per article if their referrers are different so we can't
         * just take the first ten items in the findByColumn() stack
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
            LIMIT 10
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $visitors = [];

        foreach ($statement as $stat) {
            $visitors[] = [
                'id' => $stat['user_id'],
                'occurs_at' => $stat['occurs_at'],
                'title' => $stat['title'],
                'firstname' => $stat['firstname'],
                'lastname' => $stat['lastname'],
                'fullname' => trim("{$stat['title']} {$stat['firstname']} {$stat['lastname']}"),
                'role' => $stat['role']
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput($visitors));
    }


    /**
     * See who viewed $article
     *
     * @param News\Model $article
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFull(News\Model $article): void
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
            $visitors[$stat['user_id']] = [
                'id' => $stat['user_id'],
                'occurs_at' => $stat['occurs_at'],
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
                'occurs_at' => null,
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/visitation/container.twig', [
            'article' => $article,
            'visitors' => $visitors
        ])->setTitle("Visits for {$article->getTitle()}"));
    }
}
