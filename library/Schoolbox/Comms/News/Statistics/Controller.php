<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;

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
     * @api GET /comms/news/statistics/{articleId}
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getOverview(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $accuracy = News\Statistics\Accuracy\Factory::assertAccuracy($this->getRequest()->query['accuracy']);

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

            /* Accuracy selector
             */
            'accuracies' => News\Statistics\Accuracy\Factory::ACCURACIES,
            'accuracy' => $accuracy,

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
     * when you change accuracy, only the graph updates, instead of the whole page kek
     *
     * @api GET /comms/news/statistics/{articleId}/interactions
     *
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     */
    public function getInteractionsVsTime(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $accuracy = News\Statistics\Accuracy\Factory::assertAccuracy($this->getRequest()->query['accuracy']);

        /* Conversions and Impressions vs Time,
         * Conversion Ratio vs Time
         */
        $output = [
            'interactions' => $impressionsConversionsOpens->fetchConversions($article, $accuracy),
            'conversionRatio' => $impressionsConversionsOpens->fetchConversionRatio($article, $accuracy),
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }
}
