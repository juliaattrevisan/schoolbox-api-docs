<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\IDatabase;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;

class Controller extends Base
{

    /**
     * @var IDatabase
     */
    private $db;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->db = \Schoolbox\Comms\News\Mapper::getInstance()->getDatabase();
    }

    private function buildContainer(News\Model $article)
    {
        $data = [];

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $result = $this->db->query("
            SELECT 
				CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num
            FROM comms_news_article_statistics cnas
            WHERE article_id = 1
            GROUP BY timestamp
            ORDER BY timestamp
        ");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $data[(int)$row['timestamp']] = null;
        }

        return $data;
    }


    /**
     * Add all the Dashboard Impressions
     *
     * @param News\Model $article
     * @return array
     */
    private function fetchImpressions(News\Model $article): array
    {
        $impressions = [];

        $result = $this->db->query("
            SELECT 
				CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num
            FROM comms_news_article_statistics cnas
            WHERE article_id = 1
            AND `type` = 'impression' AND referrer = 'dashboard'
            GROUP BY timestamp
            ORDER BY timestamp
        ");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $impressions[] = [(int)$row['timestamp'], (int)$row['num']];
        }

        return $impressions;
    }


    /**
     * Get all the Opens that originated from a Dashboard Impression
     *
     * @param News\Model $article
     * @return array
     */
    private function fetchConversions(News\Model $article): array
    {
        $conversions = [];

        $result = $this->db->query("
            SELECT 
				CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num
            FROM comms_news_article_statistics cnas
            WHERE article_id = 1
            AND `type` = 'open' AND referrer = 'dashboard'
            GROUP BY timestamp
            ORDER BY timestamp
        ");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $conversions[] = [(int)$row['timestamp'], (int)$row['num']];
        }

        return $conversions;
    }


    /**
     * Get all the Opens that didn't originate from anywhere in particular (unknown referrer)
     *
     * @param News\Model $article
     * @return array
     */
    private function fetchAnonymousOpens(news\Model $article): array
    {
        $opens = [];

        $result = $this->db->query("
            SELECT 
				CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num
            FROM comms_news_article_statistics cnas
            WHERE article_id = 1
            AND `type` = 'open' AND referrer IS NULL
            GROUP BY timestamp
            ORDER BY timestamp
        ");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $opens[] = [(int)$row['timestamp'], (int)$row['num']];
        }

        return $opens;
    }


    /**
     * Facade for assembling all the little pieces of the graph data
     *
     * @param News\Model $article
     * @return array
     */
    private function assembleStatistics(News\Model $article): array
    {
        return [
            'impressions' => $this->fetchImpressions($article),
            'conversions' => $this->fetchConversions($article),
            'opens' => $this->fetchAnonymousOpens($article)
        ];
    }


    /**
     * Statistical overview for $article
     *
     * Highcharts likes its dates represented as timestamps in milliseconds
     * so we take the UNIX_TIMESTAMP and smash it by 1000 and strip the microseconds bit away by casting as int
     *
     * For now, these queries also groups data according to the nearest minute
     *
     * @param News\Model $article
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getOverview(News\Model $article)
    {
        $series = $this->assembleStatistics($article);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,
            'series' => $series,
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}