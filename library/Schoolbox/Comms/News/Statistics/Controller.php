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


    /**
     * Assemble all the Impressions, Conversions and direct Opens for $article
     *
     * @param News\Model $article
     * @return array
     */
    private function fetchImpressionsConversionsOpens(News\Model $article): array
    {

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $result = $this->db->query("
            SELECT 
				CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num,
                `type`,
                `referrer`
            FROM comms_news_article_statistics cnas
            WHERE article_id = {$article->getId()}
            AND deleted_at IS NULL
            GROUP BY timestamp, `type`, referrer
            ORDER BY timestamp
        ");

        $data = [];

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $type = $row['type'];

            /* A conversion is a specific combination of Type and Referrer
             */
            if (($row['type'] === 'open') && (null !== $row['referrer'])) {
                $type = 'conversion';
            }

            $data[$type][$row['referrer']][] = [(int)$row['timestamp'], (int)$row['num']];
        }

        return $data;
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
        $series = $this->fetchImpressionsConversionsOpens($article);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,
            'series' => $series,
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}