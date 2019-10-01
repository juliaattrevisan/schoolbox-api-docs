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
     * Controller constructor.
     *
     * @param IConnector        $databaseInternal
     * @param Mapper            $statisticsMapper
     * @param Cumulative\Mapper $statisticsCumulativeMapper
     */
    public function __construct(
        IConnector $databaseInternal,
        Mapper $statisticsMapper,
        Cumulative\Mapper $statisticsCumulativeMapper
    ) {
        $this->database = $databaseInternal;
        $this->statisticsMapper = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
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
    public function getOverview(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,

            /* Length of period to record granular data
             */
            'period' => ImpressionsConversionsOpens::PERIOD_LENGTH,

            /* Overall Cumulative summary
             */
            'cumulative' => $impressionsConversionsOpens->fetchCumulative($article),

            /* Overall Referrer Demographics
             */
            'referrerDemographic' => $impressionsConversionsOpens->fetchReferrerDemographic($article),

            /* Overall RoleType Demographics
             */
            'roleTypeDemographic' => $impressionsConversionsOpens->fetchRoleTypeDemographic($article),

            /* Scale selector
             */
            'scaleOptions' => News\Statistics\Scale\Factory::SCALE_OPTIONS,
            'scale' => News\Statistics\Scale\Factory::assertScale($this->getRequest()->query['scale']),

            /* Interaction Referrers This Period
             */
            'referrersThisPeriod' => $impressionsConversionsOpens->fetchInteractionReferrers($article),

            /* Historical Dailies
             */
            'historic' => $impressionsConversionsOpens->fetchHistoric($article),
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }


    /**
     * Get the json payload to populate "Conversions, Impressions vs Time" graph so that
     * when you change scale, only the graph updates, instead of the whole page kek
     *
     * @api GET /news/statistics/{articleId}/interactions
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     */
    public function getInteractionsVsTime(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $scale = News\Statistics\Scale\Factory::assertScale($this->getRequest()->query['scale']);

        /* Conversions and Impressions vs Time,
         * Conversion Ratio vs Time
         */
        $output = [
            'interactions' => $impressionsConversionsOpens->fetchOpens($article, $scale),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }
}
