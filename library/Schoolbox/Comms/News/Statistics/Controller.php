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
     * @param News\Model $article
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getOverview(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $accuracy = $this->getRequest()->query['accuracy'];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,

//            /* Overall Cumulative summary
//             */
//            'cumulative' => $impressionsConversionsOpens->fetchCumulative($article),
//
//            /* Overall Referrer Demographics
//             */
//            'referrerDemographic' => $impressionsConversionsOpens->fetchReferrerDemographic($article),
//
            /* Accuracy selector
             */
            'accuracies' => News\Statistics\Accuracy\Factory::ACCURACIES,
            'accuracy' => $accuracy,

            /* Conversions, Impressions vs TIme
             */
            'conversions' => $impressionsConversionsOpens->fetchConversions($article, $accuracy),
//
//            /* Conversion Ratio Chronological
//             */
//            'conversionRatio' => $impressionsConversionsOpens->fetchConversionRatio($article, $accuracy),
//
//            /* Interaction Referrers This Period
//             */
//            'referrersThisPeriod' => $impressionsConversionsOpens->fetchInteractionReferrers($article),
//
//            /* Historical Dailies
//             */
//            'historic' => $impressionsConversionsOpens->fetchHistoric($article),
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}
