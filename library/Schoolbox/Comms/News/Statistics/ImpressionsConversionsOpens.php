<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Statistics\Scale\Factory;
use Schoolbox\Role;

/**
 * Class ImpressionsConversionsOpens
 *
 * Highcharts likes its dates represented as timestamps in milliseconds
 * so we take the UNIX_TIMESTAMP and smash it by 1000 and strip the microseconds bit away by casting as int
 *
 * For now, these queries also groups data according to the nearest minute
 *
 * @package Schoolbox\Comms\News\Statistics
 */
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
     * Record an open for $articleId
     * ie. $userId has witnessed $articleId
     *
     * $referrer indicates a link they clicked to initiate the open
     *
     * It can be null if someone directly typed in the url, or opened it from some random place
     * that we don't know about. Unknown referrers are disregarded
     *
     * @param News\Model  $article
     * @param int         $userId
     * @param string      $roleType
     * @param int         $roleId
     * @param null|string $referrer
     * @return bool|int[]
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveOpen(News\Model $article, int $userId, string $roleType, int $roleId, ?string $referrer)
    {
        /* Only record Opens for publicly-accessible articles
         */
        if (!$article->isPublished() && !$article->isExpired()) {
            return false;
        }

        /* Invalid referrer; ignore recording an Open
         */
        if (!(new ValidOpenReferrer())->isSatisfiedBy($referrer)) {
            return false;
        }

        /* Opens come in two flavours:
         *  - with a null referrer (which means they accessed the url directly)
         *  - with a non-null referrer (which means they came from some other link we provided)
         */
        $model = $this->statisticsMapper->createModel()
            ->setArticleId($article->getId())
            ->setUserId($userId)
            ->setReferrer($referrer)
            ->setRoleType($roleType)
            ->setRoleId($roleId)
            ->setType(Mapper::TYPE_OPEN)
            ->setOccursAt(\Alaress_Date_Format::now());

        /* Record uniques only
         * If the current (articleId, userId, referrer) tuple exists in the db
         * don't record a dupe Open, nor increment the cumulative tally
         *
         * We can't rely on the INSERT ON DUPLICATE idea because I also need to
         * increment cumulative total if (and only if) a unique Open was made
         */
        $existingOpen = $this->statisticsMapper->countByColumnList([
            'article_id' => $article->getId(),
            'type' => Mapper::TYPE_OPEN,
            'user_id' => $userId,
            'referrer' => $referrer,
            'role_type' => $roleType,
            'role_id' => $roleId,
        ]);

        /* If this (user, referrer) combination already exists, don't record duplicate data; just leave
         * Otherwise, this (user, referrer) combination indicates a new Open for this particular user
         */
        if ($existingOpen) {
            return false;
        }

        /* It's a valid referrer and $user hasn't Opened this period
         * so add an Open record and increment the cumulative total
         */
        $this->statisticsCumulativeMapper->incrementOpen($article->getId(), $roleType, $roleId, $referrer);

        return $this->statisticsMapper->save($model);
    }


    /**
     * Fetch Cumulative data across the lifetime of an article
     *
     * @param News\Model $article
     * @return array
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function fetchCumulative(News\Model $article): array
    {
        $pb = $this->statisticsCumulativeMapper->getPredicateBuilder()
            ->equals('article_id', $article->getId())
            ->lessThan('date', \Alaress_Date_Format::now()->getDatabaseDate());

        $statistics = $this->statisticsCumulativeMapper->findByPredicates($pb, [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        $summary = [

            /* First open (a specific alteration for this data set)
             */
            'date' => $statistics->count() ? $statistics->first()->getCreatedAt() : null,

            /* Total number of Opens across all time
             */
            'numOpens' => $this->calculateNumOpens($statistics),

            /* Total number of Comments
             */
            'numComments' => $this->calculateNumComments($article)
        ];

        return $summary;
    }


    /**
     * Fetch Referrer demographics across the entire lifetime of an article
     *
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
     * Aggregate values for Opens for the set of $statistics
     *
     * @param Collection|Cumulative\Model[] $statistics
     * @return array
     */
    private function summariseDailyStatistics(Collection $statistics): array
    {
        $breakdown = $this->generateDefaultBreakdown(Mapper::REFERRERS);

        /** @var Cumulative\Model $statistic */
        foreach ($statistics as $statistic) {
            $referrer = $statistic->getReferrer();

            if (null === $statistic->getReferrer()) {
                $referrer = Mapper::REFERRER_EMPTY;
            }

            $breakdown[$statistic->getType()][$referrer] += $statistic->getNum();
        }

        return [
            'date' => $statistics->count() ? $statistic->getDate() : null,
            'breakdown' => [
                'grid' => $breakdown,
            ],
        ];
    }


    /**
     * Fetch RoleType demographics across the entire lifetime of an article
     *
     * @param News\Model $article
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchRoleTypeDemographic(News\Model $article): array
    {
        $breakdown = $this->generateDefaultBreakdown(Role::getRoleTypes());

        $statement = $this->database->prepareQuery("
            SELECT 
                SUM(num) AS num,
                `type`, 
                role_type
            FROM {$this->statisticsCumulativeMapper->getTable()}
            WHERE article_id = :articleId
            GROUP BY `type`, role_type
            ORDER BY role_type;
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $breakdown[$row['type']][$row['role_type']] += $row['num'];
        }

        return $breakdown;
    }


    /**
     * Get Opens vs Time
     *
     * @param News\Model  $article
     * @param null|string $scale
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function fetchOpensVsTime(News\Model $article, ?string $scale): array
    {

        /* Determine a sane value
         */
        $strat = (new Factory())->produce($scale);

        $statement = $this->database->prepareQuery("
            SELECT
                {$strat->getSql()}
                role_type,
                COUNT(*) AS num
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, role_type
            ORDER BY timestamp ASC
        ");

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        /* Generate placeholder timestamps, superimpose actual data on top of placeholders
         */
        $range = $strat->generateDateRange(\Alaress_Date_Format::now()->addDays(-1*self::PERIOD_LENGTH), \Alaress_Date_Format::now());

        foreach (Role::getRoleTypes() as $roleType) {
            $data[$roleType] = $range;
        }

        /* There's only one Type now (Open)
         * so we can group by Role Type instead
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $data[$row['role_type']][$row['datetime']] = [
                (int)$row['timestamp'],
                (int)$row['num'],
                $row['datetime']
            ];
        }

        /* Format it for highcharts to understand
         */
        foreach (Role::getRoleTypes() as $roleType) {
            $data[$roleType] = array_values($data[$roleType]);

            /* Sort to eliminate anomolous ordering
             *
             * Sometimes data points are added that don't fall within the prescribed set of keys produced by
             * generateDateRange() and are added to the end of the stack, out of order with everything else
             *
             * First encountered setting the period to be longer than 30 days and using the Month scale
             * option but may occur if we misconfigured something
             */
            usort($data[$roleType], function($a, $b) {
                return $a[0] > $b[0];
            });
        }

        return $data;
    }


    /**
     * Calculate a integer percentage, but you can specify precision
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
     * Generate an initialised array for each of the interaction types
     * Prefill array with default values so they don't render as empty strings
     *
     * @param array $keys
     * @return array
     */
    public function generateDefaultBreakdown(array $keys): array
    {
        $defaultValues = array_fill_keys($keys, 0);

        return [
            Mapper::TYPE_OPEN => $defaultValues,
        ];
    }

    /**
     * Fetch Cumulative data per day during the lifetime of an article
     *
     * @param News\Model $article
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Alaress\Database\Exception
     */
    public function fetchHistoric(News\Model $article): array
    {
        $pb = $this->statisticsCumulativeMapper->getPredicateBuilder()
            ->equals('article_id', $article->getId())
            ->lessThan('date', \Alaress_Date_Format::now()->getDatabaseDate());

        $dailyStatistics = $this->statisticsCumulativeMapper->findByPredicates($pb, [
            'order' => [
                'date' => 'asc'
            ]
        ]);

        /* Group by date
         */
        $historic = [];

        /** @var Cumulative\Model $statistic */
        foreach ($dailyStatistics as $dailyStatistic) {
            $node = [
                'date' => $dailyStatistic->getDate()->getDatabase(),
                'numOpens' => $this->calculateNumOpens((new Collection())->add($dailyStatistic)),
                'numComments' => $this->calculateNumComments($article, $dailyStatistic->getDate()->getDatabaseDate())
            ];

            $historic[] = $node;
        }

        return $historic;
    }


    /**
     * Aggregate the number of Opens from a collection of statistic records
     *
     * @param Collection $statistics
     * @return int
     */
    private function calculateNumOpens(Collection $statistics): int
    {
        $numOpens = 0;

        /** @var Cumulative\Model $statistic */
        foreach ($statistics as $statistic) {
            $numOpens += $statistic->getNum();
        }

        return $numOpens;
    }


    /**
     * Calculate number of comments for a given article
     * @param News\Model  $article
     * @param string|null $date
     * @return int
     * @throws \Alaress\Database\Exception
     */
    private function calculateNumComments(News\Model $article, string $date = null): int
    {
        $query = '
            SELECT COUNT(*) AS numComments
            FROM comms_news_article cna
            INNER JOIN discussion_thread_comment dtc ON cna.discussion_thread_id = dtc.discussion_thread_id
            WHERE dtc.deleted_at IS NULL
            AND cna.id = :articleId
        ';

        /* Optional date filter
         */
        if ($date) {
            $query .= " AND dtc.created_at LIKE '{$date}%'";
        }

        $statement = $this->database->prepareQuery($query);

        $statement->execute([
            'articleId' => $article->getId()
        ]);

        return (int)$statement->fetch(\PDO::FETCH_ASSOC)['numComments'];
    }
}
