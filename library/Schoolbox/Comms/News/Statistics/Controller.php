<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Comms\News\Statistics
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param IConnector        $database
     * @param Mapper            $statisticsMapper
     * @param Cumulative\Mapper $statisticsCumulativeMapper
     * @param UserResolver      $userResolver
     * @param UserMapper        $userMapper
     */
    public function __construct(
        private IConnector $database,
        private Mapper $statisticsMapper,
        private Cumulative\Mapper $statisticsCumulativeMapper,
        private UserResolver $userResolver,
        private UserMapper $userMapper
    ) {
    }


    /**
     * Statistical overview for $article
     *
     * @param Model $article
     * @throws Exception
     * @throws \Alaress\Database\Exception
     * @throws InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     * @api GET /news/statistics/{articleId}
     *
     */
    public function getOverview(News\Model $article): void
    {
        $interactions = new Interactions($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $audience = $this->userResolver->findUsersForArticle($article);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,

            /* Length of period to record granular data
             */
            'period' => Interactions::PERIOD_LENGTH,

            /* Overall Cumulative summary
             */
            'cumulative' => $interactions->fetchCumulative($article, $audience),

            'audience' => count($audience),

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
            'scale' => News\Statistics\Scale\Factory::assertScale($this->getRequest()->query->get('scale')),

            /* Historical Dailies
             * Temporarily disabled
             */
            // 'historic' => $interactions->fetchHistoric($article),

            /* Recent Visitors to this article
             */
            'recentVisitors' => (new Visitation\Payload\Top10($this->statisticsMapper, $this->userMapper))->generate($article)
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
     */
    public function getVisitation(News\Model $article): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/visitation/container.twig', [
            'article' => $article,
        ])->setTitle("Visitors for {$article->getTitle()}"));
    }


    /**
     * Get the most recent 10 visitors to $article
     *
     * @param Model $article
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws Exception
     */
    public function getTop10(News\Model $article): void
    {
        $payload = (new Visitation\Payload\Top10($this->statisticsMapper, $this->userMapper))
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
