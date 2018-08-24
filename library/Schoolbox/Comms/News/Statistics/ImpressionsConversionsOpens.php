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
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Statistics\Accuracy\Factory;

class ImpressionsConversionsOpens
{

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
     * Fetch Cumulative data across the lifetime of an article
     *
     * @param News\Model $article
     * @return array
     */
    public function fetchCumulative(News\Model $article): array
    {
        $statistics = $this->statisticsCumulativeMapper->findByColumn('article_id', $article->getId(), [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        /* First open
         */
        $firstOpen = $statistics->first()->getCreatedAt();;

        /* Aggregate from daily statistics
         */
        $summary = $this->summariseDailyStatistics($statistics);

        /* Calculate overall conversion ratio
         */
        $conversionRatio = $this->calculateConversionRatio($summary['numConversions'], $summary['numImpressions']);

        /* Calculate conversion ratios for each referrer type
         */
        $referrerRatio = $this->calculateReferrerRatios($summary['breakdown']);

        return [
            'date' => $firstOpen,
            'breakdown' => [
                'grid' => $summary['breakdown']['grid'],
                'ratio' => $referrerRatio
            ],
            'numConversions' => $summary['numConversions'],
            'numImpressions' => $summary['numImpressions'],
            'conversionRatio' => $conversionRatio
        ];
    }


    /**
     * Fetch Cumulative data per day during the lifetime of an article
     *
     * @param News\Model $article
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function fetchHistoric(News\Model $article): array
    {
        $pb = $this->statisticsCumulativeMapper->getPredicateBuilder()
            ->equals('article_id', $article->getId())
            ->lessThan('date', \Alaress_Date_Format::now()->getDatabaseDate());

        $statistics = $this->statisticsCumulativeMapper->findByPredicates($pb, [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        /* Group by date
         */
        $stack = [];

        /** @var Cumulative\Model $statistic */
        foreach ($statistics as $statistic) {
            $stack[$statistic->getDate()->getDatabase()][] = $statistic;
        }

        /* Process each day's data
         */
        $historic = [];

        foreach ($stack as $date => $statistics) {
            $summary = $this->summariseDailyStatistics(new Collection($statistics));

            /* Calculate overall conversion ratio
             */
            $conversionRatio = $this->calculateConversionRatio($summary['numConversions'], $summary['numImpressions']);

            /* Calculate conversion ratios for each referrer type
             */
            $referrerRatio = $this->calculateReferrerRatios($summary['breakdown']);

            $daily = [
                'breakdown' => [
                    'grid' => $summary['breakdown']['grid'],
                    'ratio' => $referrerRatio
                ],
                'numConversions' => $summary['numConversions'],
                'numImpressions' => $summary['numImpressions'],
                'conversionRatio' => $conversionRatio
            ];

            $historic[] = $daily;
        }

        return $historic;
    }


    /**
     * Aggregate values for conversions and impressions for the set of $statistics
     *
     * @param Collection|Cumulative\Model[] $statistics
     * @return array
     */
    private function summariseDailyStatistics(Collection $statistics): array
    {

        /* Prefill array with default values so they don't render as empty strings
         */
        $defaultValues = array_fill_keys(Mapper::REFERRERS, 0);

        $breakdown = [
            Mapper::TYPE_CONVERSION => $defaultValues,
            Mapper::TYPE_IMPRESSION => $defaultValues,
            Mapper::TYPE_OPEN => $defaultValues,
        ];

        $numConversions = 0;
        $numImpressions = 0;

        /** @var Cumulative\Model $statistic */
        foreach ($statistics as $statistic) {
            $referrer = $statistic->getReferrer();

            if (null === $statistic->getReferrer()) {
                $referrer = Mapper::REFERRER_EMPTY;
            }

            $breakdown[$statistic->getType()][$referrer] = $statistic->getNum();

            if ($statistic->getType() === Mapper::TYPE_IMPRESSION) {
                $numImpressions += $statistic->getNum();
            } elseif ($statistic->getType() === Mapper::TYPE_CONVERSION) {
                $numConversions += $statistic->getNum();
            }
        }

        /* Calculate overall conversion ratio
         */
        $conversionRatio = $this->calculateConversionRatio($numConversions, $numImpressions);

        /* Calculate conversion ratios for each referrer type
         */
        $referrerRatio = $this->calculateReferrerRatios($breakdown);

        return [
            'date' => $statistic->getDate(),
            'breakdown' => [
                'grid' => $breakdown,
                'ratio' => $referrerRatio
            ],
            'numConversions' => $numConversions,
            'numImpressions' => $numImpressions,
            'conversionRatio' => $conversionRatio
        ];
    }


    /**
     * @param News\Model $article
     * @return array
     */
    public function fetchReferrerDemographic(News\Model $article): array
    {
        $statistics = $this->statisticsCumulativeMapper->findByColumn('article_id', $article->getId(), [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        $summary = $this->summariseDailyStatistics($statistics);

        return $summary['breakdown']['grid'];
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
    public function fetchConversionRatio(News\Model $article, string $accuracy): array
    {
        /* Determine a sane value
         */
        $strat = (new Factory())->produce($accuracy);

        /* Get all the timestamps that have content - because of the way highcharts stacks its data
         * I need to initialise the container with all the possibilities and stack categorised data in-order
         */
        $statement = $this->database->prepareQuery("
            SELECT
                {$strat->getSql($accuracy)}
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
        $periodLength = self::PERIOD_LENGTH;

        $statement = $this->database->prepareQuery("
            SELECT 
                SUM(num) AS num, 
                `type`,
                1000*UNIX_TIMESTAMP(CONCAT(MAX(`date`), ' 00:00:00')) AS start
            FROM {$this->statisticsCumulativeMapper->getTable()}
            WHERE article_id = :articleId
            AND `date` <= CURRENT_DATE - INTERVAL {$periodLength} DAY
            AND deleted_at IS NULL
            GROUP BY `type`
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)){
            $initialConditions[$row['type']] = $row['num'];
            $initialConditions['start'] = $row['start'];
        }

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
    public function fetchConversions(News\Model $article, ?string $accuracy): array
    {

        /* Determine a sane value
         */
        $strat = (new Factory())->produce($accuracy);

        $statement = $this->database->prepareQuery("
            SELECT
                {$strat->getSql($accuracy)}
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

        /* Generate placeholder timestamps, superimpose actual data on top of placeholders
         */
        $range = $strat->generateDateRange(\Alaress_Date_Format::now()->addDays(-1*self::PERIOD_LENGTH), \Alaress_Date_Format::now());

        $data = [
            Mapper::TYPE_CONVERSION => $range,
            Mapper::TYPE_IMPRESSION => $range,
            Mapper::TYPE_OPEN => $range
        ];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $data[$row['type']][$row['datetime']] = [ (int)$row['timestamp'], (int)$row['num'], $row['datetime']];
        }

        /* Format it for highcharts to understand
         */
        foreach ([Mapper::TYPE_CONVERSION, Mapper::TYPE_IMPRESSION, Mapper::TYPE_OPEN] as $type) {
            $data[$type] = array_values($data[$type]);
        }

        return $data;
    }


    /**
     * Fetch the impressions/conversions for each referrer type for all the granular data in this period
     *
     * @param News\Model  $article
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchInteractionReferrers(News\Model $article): array
    {
        $statement = $this->database->prepareQuery("
            SELECT
                COUNT(*) AS num,
                `type`,
                `referrer`
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY `type`, referrer
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        $breakdown = [
            Mapper::TYPE_CONVERSION => array_fill_keys(Mapper::REFERRERS, 0),
            Mapper::TYPE_IMPRESSION => array_fill_keys(Mapper::REFERRERS, 0),
            Mapper::TYPE_OPEN => array_fill_keys(Mapper::REFERRERS, 0),
        ];

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
            $breakdown[$type][$referrer] = (int)$row['num'];
        }

        return array_map(function($values) {
            return array_values($values);
        }, $breakdown);
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
