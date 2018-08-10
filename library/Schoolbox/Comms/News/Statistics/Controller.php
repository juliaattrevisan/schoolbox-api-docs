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
     * Assemble all the Impressions, Conversions and direct Opens for $article
     *
     * @param News\Model $article
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function fetchImpressionsConversionsOpens(News\Model $article): array
    {
        /* This controls how timestamps are grouped together, this increasing or decreasing the accuracy
         * of the dataset in exchange for perhaps more meaningful interpretations.
         *
         * For example, 60 seconds (accuracy=60) might be too granular for some people.
         * Such might prefer collating five minutes worth of data instead (accuracy=300)
         *
         * Events that occur within $accuracy seconds of each other are aggregated together.
         */
        $accuracy = 60;

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $statement = $this->database->prepareQuery("
            SELECT 
                CAST(1000*UNIX_TIMESTAMP(CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:%i'), ':00')) AS UNSIGNED) AS timestamp,
                1000*(UNIX_TIMESTAMP(created_at) DIV :accuracy) AS accuracy,
                COUNT(*) AS num,
                `type`,
                `referrer`
            FROM comms_news_article_statistics cnas
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY accuracy, `type`, referrer
            ORDER BY timestamp
        ");

        $statement->execute([
            'accuracy' => $accuracy,
            'articleId' => $article->getId()
        ]);

        $data = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $referrer = $row['referrer'];

            /* A conversion is a specific combination of Type and Referrer
             */
            if (($referrer !== null) && ($row['type'] === Mapper::TYPE_OPEN)) {
                $type = Mapper::TYPE_CONVERSION;
            }

            /* If referrers are of an unknown type, just throw them into an arbitrary "unknown" category
             */
            if ($referrer === null) {
                $referrer = Mapper::REFERRER_EMPTY;
            }

            /* Stack it
             */
            $data[$type][$referrer][] = [(int)$row['timestamp'], (int)$row['num']];
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
     * @throws \Alaress\Database\Exception
     */
    public function getOverview(News\Model $article)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article,
            'series' => $this->fetchImpressionsConversionsOpens($article),
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}