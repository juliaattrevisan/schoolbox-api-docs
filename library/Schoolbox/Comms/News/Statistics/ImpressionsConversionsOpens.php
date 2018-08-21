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
    private const DEFAULT_ACCURACY = '10min';

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
        '1min'  => "SUBSTRING(occurs_at FROM 1 FOR 16),         ':00'",
        '10min' => "SUBSTRING(occurs_at FROM 1 FOR 15),        '0:00'",
        'hour'  => "SUBSTRING(occurs_at FROM 1 FOR 13),      ':00:00'",
        'day'   => "SUBSTRING(occurs_at FROM 1 FOR 11),    '00:00:00'",
        'month' => "SUBSTRING(occurs_at FROM 1 FOR 7), '-01 00:00:00'",
    ];

    /**
     * Number of days in this period
     */
    public const PERIOD_LENGTH = 30;

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
     * @param News\Model  $article
     * @param int         $userId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveImpression(News\Model $article, int $userId, ?string $referrer)
    {
        /* Only record Impressions for publicly-accessible articles
         */
        if (!$article->isPublished()) {
            return false;
        }

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
            'article_id' => $article->getId(),
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
        $this->statisticsCumulativeMapper->incrementImpression($article->getId(), $referrer);

        return $this->statisticsMapper->save($this->statisticsMapper->createModel()
            ->setArticleId($article->getId())
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
     * @param News\Model  $article
     * @param int         $userId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveConversionOpen(News\Model $article, int $userId, ?string $referrer)
    {
        /* Only record Conversions for publicly-accessible articles
         */
        if (!$article->isPublished()) {
            return false;
        }

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
            'article_id' => $article->getId(),
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
            ->setArticleId($article->getId())
            ->setUserId($userId)
            ->setReferrer($referrer)
            ->setOccursAt(\Alaress_Date_Format::now());

        if (null !== $referrer) {

            /* It's a valid referrer and $user hasn't been Converted this period
             * so add a Conversion record and increment the cumulative total
             */
            $this->statisticsCumulativeMapper->incrementConversion($article->getId(), $referrer);

            $model->setType(Mapper::TYPE_CONVERSION);
        } else {

            /* It's a valid referrer and $user hasn't Opened this period
             * so add an Open record and increment the cumulative total
             */
            $this->statisticsCumulativeMapper->incrementOpen($article->getId(), $referrer);

            $model->setType(Mapper::TYPE_OPEN);
        }

        return $this->statisticsMapper->save($model);
    }


    /**
     * Fetch Cumulative data
     *
     * @param News\Model $article
     * @return array
     */
    public function fetchCumulative(News\Model $article): array
    {
        $cumulativeData = $this->statisticsCumulativeMapper->findByColumn('article_id', $article->getId(), [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        /* First open
         */
        $firstOpen = null;

        /* Raw number of Conversions and Impressions
         */
        $numImpressions = 0;
        $numConversions = 0;
        $breakdown = [];

        foreach ($cumulativeData as $current) {
            if (null === $firstOpen) {
                $firstOpen = $current->getDate();
            }

            $numImpressions += $current->findTotalImpressions();
            $numConversions += $current->findTotalConversions();

            $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_DASHBOARD] += $current->getNumConversionsDashboard();
            $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_HOMEPAGE] += $current->getNumConversionsHomepage();
            $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_INDEX] += $current->getNumConversionsIndex();
            $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_ARCHIVE] += $current->getNumConversionsArchive();
            $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_EMAIL] += $current->getNumConversionsEmail();

            $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_DASHBOARD] += $current->getNumImpressionsDashboard();
            $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_HOMEPAGE] += $current->getNumImpressionsHomepage();
            $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_INDEX] += $current->getNumImpressionsIndex();
            $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_ARCHIVE] += $current->getNumImpressionsArchive();
            $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_EMAIL] += $current->getNumImpressionsEmail();

            $breakdown[Mapper::TYPE_OPEN] += $current->getNumOpens();
        }

        /* Calculate overall conversion ratio
         */
        $conversionRatio = 0.00;

        if ($numImpressions && $numConversions) {
            $conversionRatio = $this->percentage($numConversions, $numImpressions, 1);
        }

        /* Calculate Conversion ratios for each referrer type
        */
        $referrers = Mapper::REFERRERS;

        $referrerRatio = [];

        foreach ($referrers as $referrer) {
            $referrerRatio[$referrer] = $this->percentage($breakdown[Mapper::TYPE_CONVERSION][$referrer], $breakdown[Mapper::TYPE_IMPRESSION][$referrer]);
        }

        return [
            'firstOpen' => $firstOpen,
            'breakdown' => [
                'grid' => $breakdown,
                'ratio' => $referrerRatio
            ],
            'numImpressions' => $numImpressions,
            'numConversions' => $numConversions,
            'conversionRatio' => $conversionRatio
        ];
    }


    /**
     * @param News\Model $article
     * @return array
     */
    public function fetchHistoric(News\Model $article): array
    {
        $statistics = $this->statisticsCumulativeMapper->findByColumn('article_id', $article->getId(), [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        $daily = [];

        foreach ($statistics as $statistic) {
            $daily[] = $this->summariseDailyStatistics($statistic);
        }

        return $daily;
    }


    /**
     * @param Cumulative\Model $statistic
     * @return array
     */
    private function summariseDailyStatistics(Cumulative\Model $statistic): array
    {

        /* Raw number of Conversions and Impressions
         */
        $numImpressions = 0;
        $numConversions = 0;
        $breakdown = [];

        $numImpressions += $statistic->findTotalImpressions();
        $numConversions += $statistic->findTotalConversions();

        $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_DASHBOARD] += $statistic->getNumConversionsDashboard();
        $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_HOMEPAGE] += $statistic->getNumConversionsHomepage();
        $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_INDEX] += $statistic->getNumConversionsIndex();
        $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_ARCHIVE] += $statistic->getNumConversionsArchive();
        $breakdown[Mapper::TYPE_CONVERSION][Mapper::REFERRER_EMAIL] += $statistic->getNumConversionsEmail();

        $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_DASHBOARD] += $statistic->getNumImpressionsDashboard();
        $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_HOMEPAGE] += $statistic->getNumImpressionsHomepage();
        $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_INDEX] += $statistic->getNumImpressionsIndex();
        $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_ARCHIVE] += $statistic->getNumImpressionsArchive();
        $breakdown[Mapper::TYPE_IMPRESSION][Mapper::REFERRER_EMAIL] += $statistic->getNumImpressionsEmail();

        $breakdown[Mapper::TYPE_OPEN] += $statistic->getNumOpens();

        /* Calculate overall conversion ratio
         */
        $conversionRatio = $this->calculateConversionRatio($numConversions, $numImpressions);

        /* Calculate conversion ratios for each referrer type
         */
        $referrerRatio = $this->calculateReferrerRatios($breakdown);

        return [
            'breakdown' => [
                'grid' => $breakdown,
                'ratio' => $referrerRatio
            ],
            'numImpressions' => $numImpressions,
            'numConversions' => $numConversions,
            'conversionRatio' => $conversionRatio
        ];
    }


    /**
     * @param News\Model $article
     * @return array
     */
    public function fetchReferrerDemographic(News\Model $article): array
    {

        /** @var Cumulative\Model $model */
        try {
            $model = $this->statisticsCumulativeMapper->findOneByColumn('article_id', $article->getId());

            $impressionsTotal = $model->findTotalImpressions();
            $conversionsTotal = $model->findTotalConversions();

            $data = [
                Mapper::TYPE_IMPRESSION => [
                    Mapper::REFERRER_DASHBOARD => $this->percentage($model->getNumImpressionsDashboard(), $impressionsTotal),
                    Mapper::REFERRER_HOMEPAGE => $this->percentage($model->getNumImpressionsHomepage(), $impressionsTotal),
                    Mapper::REFERRER_INDEX => $this->percentage($model->getNumImpressionsIndex(), $impressionsTotal),
                    Mapper::REFERRER_ARCHIVE => $this->percentage($model->getNumImpressionsArchive(), $impressionsTotal),
                    Mapper::REFERRER_EMAIL => $this->percentage($model->getNumImpressionsEmail(), $impressionsTotal),
                ],
                Mapper::TYPE_CONVERSION => [
                    Mapper::REFERRER_DASHBOARD => $this->percentage($model->getNumConversionsDashboard(), $conversionsTotal),
                    Mapper::REFERRER_HOMEPAGE => $this->percentage($model->getNumConversionsHomepage(), $conversionsTotal),
                    Mapper::REFERRER_INDEX => $this->percentage($model->getNumConversionsIndex(), $conversionsTotal),
                    Mapper::REFERRER_ARCHIVE => $this->percentage($model->getNumConversionsArchive(), $conversionsTotal),
                    Mapper::REFERRER_EMAIL => $this->percentage($model->getNumConversionsEmail(), $conversionsTotal),
                ]
            ];

//            $data = [
//                Mapper::TYPE_IMPRESSION => [
//                    Mapper::REFERRER_DASHBOARD => $model->getNumImpressionsDashboard(),
//                    Mapper::REFERRER_HOMEPAGE => $model->getNumImpressionsHomepage(),
//                    Mapper::REFERRER_INDEX => $model->getNumImpressionsIndex(),
//                    Mapper::REFERRER_ARCHIVE => $model->getNumImpressionsArchive(),
//                    Mapper::REFERRER_EMAIL => $model->getNumImpressionsEmail(),
//                ],
//                Mapper::TYPE_CONVERSION => [
//                    Mapper::REFERRER_DASHBOARD => $model->getNumConversionsDashboard(),
//                    Mapper::REFERRER_HOMEPAGE => $model->getNumConversionsHomepage(),
//                    Mapper::REFERRER_INDEX => $model->getNumConversionsIndex(),
//                    Mapper::REFERRER_ARCHIVE => $model->getNumConversionsArchive(),
//                    Mapper::REFERRER_EMAIL => $model->getNumConversionsEmail(),
//                ]
//            ];

        } catch (\Schoolbox\Mapper\Exception $e) {
            $data = [
                Mapper::TYPE_IMPRESSION => array_fill_keys(Mapper::REFERRERS, 0),
                Mapper::TYPE_CONVERSION => array_fill_keys(Mapper::REFERRERS, 0),
            ];
        }


        return $data;
    }


    /**
     * The average conversion ratio over time
     *
     * Fetch data set separately from its previous home in fetchInteractionReferrers()
     *
     * @param News\Model  $article
     * @param null|string $accuracy
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchConversionRatio(News\Model $article, ?string $accuracy = self::DEFAULT_ACCURACY): array
    {
        /* Determine a sane value
         */
        $accuracySql = self::ACCURACIES[$this->assertAccuracy($accuracy)];

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $statement = $this->database->prepareQuery("
            SELECT 
                1000*UNIX_TIMESTAMP(CONCAT({$accuracySql})) AS timestamp,
                COUNT(*) AS num,
                `type`
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, `type`
            ORDER BY timestamp
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $interactions = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $interactions[(int)$row['timestamp']][$row['type']] += $row['num'];
        }

        /* Figure out the average conversion ratio over time
         *
         * First, find out if there were any data points prior to this period
         * If there were, average all those and use that as a starting point
         * Otherwise just start at zero (coalesced in sql)
         */
        $statement = $this->database->prepareQuery("
            SELECT 
                COALESCE(SUM(num_conversions_dashboard + num_conversions_homepage + num_conversions_index + num_conversions_archive + num_conversions_email), 0) AS conversion,
                COALESCE(SUM(num_impressions_dashboard + num_impressions_homepage + num_impressions_index + num_impressions_archive + num_impressions_email), 0) AS impression,
                1000*UNIX_TIMESTAMP(CONCAT(MAX(`date`), ' 00:00:00')) AS start
            FROM {$this->statisticsCumulativeMapper->getTable()}
            WHERE article_id = :articleId
            AND `date` <= CURRENT_DATE - INTERVAL ".self::PERIOD_LENGTH." DAY
            AND deleted_at IS NULL
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $initialConditions = $statement->fetch(\PDO::FETCH_ASSOC);

        $numerator = $initialConditions['conversion'];
        $denominator = $initialConditions['impression'];

        /* Aggregate by using science
         * If initialConditions[start] doesn't exist, it's because there was no previous cumulative data
         * from which to draw initial conditions so the series should just start at the first value of
         * the current granular data set.
         */
        $data = [];

        if ($initialConditions['start']) {
            $data = [
                [ (int)$initialConditions['start'], $this->percentage($numerator, $denominator) ]
            ];
        }

        foreach ($interactions as $timestamp => $current) {
            $numerator += $current['conversion'];
            $denominator += $current['impression'];

            $data[] = [$timestamp, $this->percentage($numerator, $denominator)];
        }

        return $data;
    }


    /**
     * Get Conversion and Impressions vs Time
     *
     * @param News\Model  $article
     * @param null|string $accuracy
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchConversions(News\Model $article, ?string $accuracy = self::DEFAULT_ACCURACY): array
    {
        /* Determine a sane value
         */
        $accuracySql = self::ACCURACIES[$this->assertAccuracy($accuracy)];

        $statement = $this->database->prepareQuery("
            SELECT 
                1000*UNIX_TIMESTAMP(CONCAT({$accuracySql})) AS timestamp,
                `type`,
                COUNT(*) AS num
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, `type`
            ORDER BY timestamp
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $data = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $data[$row['type']][] = [(int)$row['timestamp'], (int)$row['num']];
        }

        return $data;
    }


    /**
     * Assemble all the granular Impressions, Conversions and direct Opens for $article
     *
     * @param News\Model  $article
     * @param string|null $accuracy
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchInteractionReferrers(News\Model $article, ?string $accuracy = self::DEFAULT_ACCURACY): array
    {
        /* Determine a sane value
         */
        $accuracySql = self::ACCURACIES[$this->assertAccuracy($accuracy)];

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $statement = $this->database->prepareQuery("
            SELECT 
                1000*UNIX_TIMESTAMP(CONCAT({$accuracySql})) AS timestamp,
                COUNT(*) AS num,
                `type`,
                `referrer`
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, `type`, referrer
            ORDER BY timestamp
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $data = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $referrer = $row['referrer'];

            /* If referrers are of an unknown type, just throw them into an arbitrary "unknown" category
             */
            if ($referrer === null) {
                $referrer = Mapper::REFERRER_EMPTY;
            }

            /* Stack it
             */
            $data[$type][$referrer][] = [(int)$row['timestamp'], (int)$row['num'], date("Y-m-d H:i:s", $row['timestamp']/1000)];
        }

        return $data;
    }


    /**
     * Calculate a integer percentage
     *
     * @param null|int $numerator
     * @param null|int $denominator
     * @param int      $precision
     * @return float
     */
    private function percentage(?int $numerator, ?int $denominator, int $precision = 0): float
    {
        $percentage = $denominator ? round(100*$numerator/$denominator, $precision) : 0;

        return $percentage > 100 ? 100 : $percentage;
    }


    /**
     * @param null|string $accuracy
     * @return null|string
     */
    private function assertAccuracy(?string $accuracy = self::DEFAULT_ACCURACY): ?string
    {
        if (!\array_key_exists($accuracy, self::ACCURACIES)) {
            $accuracy = self::DEFAULT_ACCURACY;
        }

        return $accuracy;
    }

    /**
     * Calculate overall conversion ratio
     *
     * @param int $numConversions
     * @param int $numImpressions
     * @return float
     */
    private function calculateConversionRatio(int $numConversions, int $numImpressions): float
    {
        $conversionRatio = 0.00;

        if ($numConversions && $numImpressions) {
            $conversionRatio = $this->percentage($numConversions, $numImpressions, 1);
        }

        return $conversionRatio;
    }


    /**
     * Calculate Conversion ratios for each referrer type
     *
     * @param array $breakdown
     * @return array
     */
    private function calculateReferrerRatios(array $breakdown): array
    {
        $referrers = Mapper::REFERRERS;

        $referrerRatio = [];

        foreach ($referrers as $referrer) {
            $referrerRatio[$referrer] = $this->percentage($breakdown[Mapper::TYPE_CONVERSION][$referrer], $breakdown[Mapper::TYPE_IMPRESSION][$referrer]);
        }

        return $referrerRatio;
    }
}
