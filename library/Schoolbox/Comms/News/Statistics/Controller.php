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
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \PDOException
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

            /* Recent Visitors to this article
             */
            'recentVisitors' => (new Visitation\Payload\Top10($this->statisticsMapper))->generate($article)
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
     * @param News\Model $article
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getVisitation(News\Model $article): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/visitation/container.twig', [
            'article' => $article,
        ])->setTitle("Visits for {$article->getTitle()}"));
    }


    /**
     * Get the most recent 10 visitors to $article
     *
     * @param News\Model $article
     * @throws \PDOException
     */
    public function getTop10(News\Model $article): void
    {
        $payload = (new Visitation\Payload\Top10($this->statisticsMapper))
            ->generate($article);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * See who viewed $article
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getFull(News\Model $article): void
    {
        $payload = (new Visitation\Payload\Full($this->statisticsMapper, $this->userResolver, $this->userMapper))
            ->generate($article);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }
}
