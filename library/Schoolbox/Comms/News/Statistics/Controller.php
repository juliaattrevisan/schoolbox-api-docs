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
     * Controller constructor.
     *
     * @param IConnector $databaseInternal
     */
    public function __construct(IConnector $databaseInternal)
    {
        $this->database = $databaseInternal;
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
        $impressionsConversionsOpens = new ImpressionsConversionsOpens($this->database);
        $accuracy = $this->getRequest()->query['accuracy'];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,
            'accuracies' => ImpressionsConversionsOpens::ACCURACIES,
            'accuracy' => $accuracy,
            'series' => $impressionsConversionsOpens->fetch($article, $accuracy),
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}
