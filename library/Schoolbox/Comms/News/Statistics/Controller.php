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
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Exception
     */
    public function getOverview(News\Model $article)
    {
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database, $this->statisticsMapper, $this->statisticsCumulativeMapper);
        $accuracy = $this->getRequest()->query['accuracy'];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,
            'accuracies' => ImpressionsConversionsOpens::ACCURACIES,
            'accuracy' => $accuracy,
            'series' => $impressionsConversionsOpens->fetchGranular($article, $accuracy),
            'cumulative' => $impressionsConversionsOpens->fetchCumulative($article)
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}
