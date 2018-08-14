<?php
/**
 * ImpressionsConversionsOpens
 *
 * Highcharts likes its dates represented as timestamps in milliseconds
 * so we take the UNIX_TIMESTAMP and smash it by 1000 and strip the microseconds bit away by casting as int
 *
 * For now, these queries also groups data according to the nearest minute
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Comms\News;

class ImpressionsConversionsOpens
{

    /**
     * Default accuracy
     */
    private const DEFAULT_ACCURACY = 'minute';

    /**
     * The allowed accuracies for a user to choose
     *
     * This controls how timestamps are grouped together, this increasing or decreasing the accuracy
     * of the dataset in exchange for perhaps more meaningful interpretations.
     *
     * For example, 60 seconds (accuracy=60) might be too granular for some people.
     * Such might prefer collating five minutes worth of data instead (accuracy=300)
     *
     * Events that occur within $accuracy seconds of each other are aggregated together.
     *
     * @var array
     */
    public const ACCURACIES = [
        'minute' => 'CONCAT(DATE_FORMAT(occurs_at, \'%Y-%m-%d %H:%i\'), \':00\')',
        'hour' => 'CONCAT(DATE_FORMAT(occurs_at, \'%Y-%m-%d %H\'), \':00:00\')',
        'day' => 'CONCAT(DATE_FORMAT(occurs_at, \'%Y-%m-%d\'), \' 00:00:00\')',
        'month' => 'CONCAT(DATE_FORMAT(occurs_at, \'%Y-%m\'), \'-01 00:00:00\')',
    ];


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
     * Record an Impression for $articleId
     * ie. $userId has witnessed $articleId
     *
     * $referrer indicates a place where they saw the Impression and can't be null for this use case
     * Unknown referrers are disregarded
     *
     * @param int         $articleId
     * @param int         $userId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveImpression(int $articleId, int $userId, ?string $referrer)
    {

        /* Invalid referrer; ignore recording an Impression
         */
        if (!(new ValidImpressionReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        /* Record uniques only
         * If the current (articleId, userId, referrer) tuple exists in the db
         * don't record a dupe Impression, nor increment the cumulative tally
         *
         * We can't rely on the INSERT ON DUPLICATE idea because I also need to
         * increment cumulative total if (and only if) a unique Impression was made
         */
        $existing = $this->statisticsMapper->countByColumnList([
            'article_id' => $articleId,
            'type' => Mapper::TYPE_IMPRESSION,
            'user_id' => $userId,
            'referrer' => $referrer
        ]);

        if ($existing) {
            return false;
        }

        /* It's a valid referrer and $user hasn't been Impressed this period
         * so add an Impression record and increment the cumulative total
         */
        $this->statisticsCumulativeMapper->incrementImpression($articleId, $referrer);

        return $this->statisticsMapper->save($this->statisticsMapper->createModel()
            ->setArticleId($articleId)
            ->setUserId($userId)
            ->setType(Mapper::TYPE_IMPRESSION)
            ->setReferrer($referrer)
            ->setOccursAt(\Alaress_Date_Format::now()));
    }


    /**
     * Record an impression for $articleId
     * ie. $userId has witnessed $articleId
     *
     * $referrer indicates a link they clicked to initiate the open
     *
     * It can be null if someone directly typed in the url, or opened it from some random place
     * that we don't know about. Unknown referrers are disregarded
     *
     * @param int         $articleId
     * @param int         $userId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function saveConversionOpen(int $articleId, int $userId, ?string $referrer)
    {
        /* Invalid referrer; ignore recording an Open
         */
        if (!(new ValidOpenReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        /* Record uniques only
         * If the current (articleId, userId, referrer) tuple exists in the db
         * don't record a dupe Conversion, nor increment the cumulative tally
         *
         * We can't rely on the INSERT ON DUPLICATE idea because I also need to
         * increment cumulative total if (and only if) a unique Conversion was made
         */
        $existing = $this->statisticsMapper->countByColumnList([
            'article_id' => $articleId,
            'type' => Mapper::TYPE_CONVERSION,
            'user_id' => $userId,
            'referrer' => $referrer
        ]);

        if ($existing) {
            return false;
        }

        /* Opens come in two flavours:
         *  - with a null referrer (which means they accessed the url directly)
         *  - with a non-null referrer (which means they came from some other link we provided)
         *
         * Null referrers (direct opens) are recorded separately from Conversions which are
         * opens that occur with a non-null referrer.
         */
        $model = $this->statisticsMapper->createModel()
            ->setArticleId($articleId)
            ->setUserId($userId)
            ->setReferrer($referrer)
            ->setOccursAt(\Alaress_Date_Format::now());

        if (null !== $referrer) {

            /* It's a valid referrer and $user hasn't been Converted this period
             * so add a Conversion record and increment the cumulative total
             */
            $this->statisticsCumulativeMapper->incrementConversion($articleId, $referrer);

            $model->setType(Mapper::TYPE_CONVERSION);
        } else {

            /* It's a valid referrer and $user hasn't Opened this period
             * so add an Open record and increment the cumulative total
             */
            $this->statisticsCumulativeMapper->incrementOpen($articleId, $referrer);

            $model->setType(Mapper::TYPE_OPEN);
        }

        return $this->statisticsMapper->save($model);
    }


    /**
     * Assemble all the granular Impressions, Conversions and direct Opens for $article
     *
     * @param News\Model  $article
     * @param string|null $accuracy
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchGranular(News\Model $article, ?string $accuracy = self::DEFAULT_ACCURACY): array
    {

        /* Determine a sane value
         */
        if (!\array_key_exists($accuracy, self::ACCURACIES)) {
            $accuracy = self::DEFAULT_ACCURACY;
        }

        $accuracySql = self::ACCURACIES[$accuracy];

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $statement = $this->database->prepareQuery("
            SELECT 
                CAST(1000*UNIX_TIMESTAMP({$accuracySql}) AS UNSIGNED) AS timestamp,
                COUNT(*) AS num,
                `type`,
                `referrer`
            FROM comms_news_article_statistics cnas
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, `type`, referrer
            ORDER BY timestamp
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $data = [];
        $conversionRatio = [];

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

            /* Summate for a conversion ratio
             */
            $conversionRatio[(int)$row['timestamp']][$type]++;
        }

        /* Figure out conversion ratio per timestamp
         *
         * If you have a Conversion without an Impression (ie. someone sends you a copy
         * of their link and you visit it directly) record this as a 0% conversion - you
         * didn't see the impression to open it, you just did it directly.
         */
        foreach ($conversionRatio as $timestamp => $values) {
            $ratio = 0;

            /* If there were more Conversions than Impressions, max it at 100%
             * Someone visited a referred url without having been Impressed
             *
             * Otherwise, if there were Impressions, assume Conversions occur because of Impressions
             * and calculate a ratio accordingly
             */
            if ($values['impression'] && $values['conversion'] > $values['impression']) {
                $ratio = 100;
            } elseif ($values['impression']) {
                $ratio = round(100 * (float)$values['conversion'] / (float)$values['impression'], 1);
            }

            $data['conversionRatio'][] = [(int)$timestamp, $ratio];
        }

        return $data;
    }


    /**
     * Fetch Cumulative data
     *
     * @param News\Model $article
     * @return array
     */
    public function fetchCumulative(News\Model $article): array
    {

        /* First open
         */
        $firstOpen = null;

        /* Raw number of Conversions and Impressions
         */
        $numImpressions = 0;
        $numConversions = 0;

        $cumulativeData = $this->statisticsCumulativeMapper->findByColumn('article_id', $article->getId(), [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        foreach ($cumulativeData as $current) {
            if (null === $firstOpen) {
                $firstOpen = $current->getDate();
            }

            $numImpressions += $current->findTotalImpressions();
            $numConversions += $current->findTotalConversions();
        }

        /* Calculate ratio
         */
        $conversionRatio = 0.00;

        if ($numImpressions && $numConversions) {
            $conversionRatio = round(100*$numConversions/$numImpressions, 1);
        }

        return [
            'firstOpen' => $firstOpen,
            'numImpressions' => $numImpressions,
            'numConversions' => $numConversions,
            'conversionRatio' => $conversionRatio
        ];
    }
}
