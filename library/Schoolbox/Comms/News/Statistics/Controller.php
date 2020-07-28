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
     * See who viewed $article
     *
     * @param News\Model $article
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getVisitation(News\Model $article): void
    {

        /* The user ids who were audienced
         */
        $currentlyAudiencedIds = $this->userResolver->findUsersForArticle($article);

        /* The user ids who visited at least once
         */
        $stats = $this->statisticsMapper->findByColumn('article_id', $article->getId());
        $visitorIds = $stats->getPropertyValues('userId');

        /* The union of both audienced ids and visitor ids shows everyone who should
         * actually appear in the report; index by user id for lookupping
         */
        $userIds = array_merge($currentlyAudiencedIds, $visitorIds);
        $users = $this->userMapper->findByColumn('id', $userIds)->indexBy('id')->getAll();

        /* Process Statistics instances to find most recent visit for each person who visited
         * Initialise the stack first
         */
        $visitors = [];

        foreach ($users as $user) {
            $visitors[$user->getId()] = [
                'id' => $user->getId(),
                'role' => $user->getRole()->getName(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'fullname' => $user->getFullname(),
                'occursAt' => null
            ];
        }

        /* Sort by surname
         */
        uasort($visitors, static function ($a, $b) {
            if ($a['lastname'] === $b['lastname']) {
                return $a['firstname'] > $b['firstname'];
            }

            return ($a['lastname'] > $b['lastname']);
        });

        /* Then superposition any visitation data on top of that stack
         */
        foreach ($stats as $stat) {
            $visitorId = $stat->getUserId();

            if (null === $visitors[$visitorId]['occursAt'] || $visitors[$visitorId]['occursAt']['timestamp'] < $stat->getOccursAt()->getUnixTimestamp()) {

                /* First time encountering this user id, so initialise their visitation date
                 * Or, compare dates and only remember the more recent one
                 */
                $visitors[$visitorId]['occursAt'] = [
                    'timestamp' => $stat->getOccursAt()->getUnixTimestamp(),
                    'date' => $stat->getOccursAt()->getShort()
                ];
            }

            /* TODO Edge case
             *  Does the hyperdrive know about deleted users/do we care about deleted users?
             */
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/visitation/container.twig', [
            'article' => $article,
            'users' => $users,
            'visitors' => $visitors
        ])->setTitle("Visits for {$article->getTitle()}"));
    }
}
