<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

use Alaress\Database\Connector\IConnector;
use PDOException;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Statistics\Scale\Factory;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Role;

/**
 * Class Interactions
 *
 * Highcharts likes its dates represented as timestamps in milliseconds
 * so we take the UNIX_TIMESTAMP and smash it by 1000 and strip the microseconds bit away by casting as int
 *
 * For now, these queries also groups data according to the nearest minute
 *
 * @package Schoolbox\Comms\News\Statistics
 */
class Interactions
{

    /**
     * Number of days in this period
     */
    public const PERIOD_LENGTH = 30;

    /**
     * This threshold indicates how long to wait before we can record more data for this open, in days
     *
     * Default: you have to wait one day (60*60*24 seconds) between visits for it to start recording more data
     */
    private const UNIQUE_VISIT_THRESHOLD = 60*60;

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
     * Interactions constructor.
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
        $this->database                   = $databaseInternal;
        $this->statisticsMapper           = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
    }


    /**
     * Record an open for $articleId
     * ie. $userId has loaded the detail screen for $articleId
     *
     * $referrer indicates a link they clicked to initiate the open
     *
     * It can be null if someone directly typed in the url, or opened it from some random place
     * that we don't know about. Unknown referrers are disregarded
     *
     * @param News\Model           $article
     * @param \Alaress_Date_Format $date
     * @param int                  $userId
     * @param string               $roleType
     * @param int                  $roleId
     * @param null|string          $referrer
     * @return bool|int[]
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function saveOpen(News\Model $article, \Alaress_Date_Format $date, int $userId, string $roleType, int $roleId, ?string $referrer)
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

        /* Record uniques only
         * If the current (articleId, userId, referrer) tuple exists in the db
         * don't record a dupe Open, nor increment the cumulative tally
         *
         * We can't rely on the INSERT ON DUPLICATE idea because I also need to
         * increment cumulative total if (and only if) a unique Open was made
         */
        try {
            $open = $this->statisticsMapper->findOneByColumnList(
                [
                    'article_id' => $article->getId(),
                    'type'       => Mapper::TYPE_OPEN,
                    'user_id'    => $userId,
                    'referrer'   => $referrer,
                    'role_type'  => $roleType,
                    'role_id'    => $roleId,
                ]
            );

            /* If this (user, referrer) combination already exists, don't record duplicate data; just leave
             * Otherwise, this (user, referrer) combination indicates a new Open for this particular user
             */
            if ($open && $open->getOccursAt()->diffInSeconds($date) < self::UNIQUE_VISIT_THRESHOLD) {
                return false;
            }
        } catch (NotFound $e) {
            $open = $this->statisticsMapper->createModel()
                                           ->setArticleId($article->getId())
                                           ->setUserId($userId)
                                           ->setReferrer($referrer)
                                           ->setRoleType($roleType)
                                           ->setRoleId($roleId)
                                           ->setType(Mapper::TYPE_OPEN);
        }

        /* A race condition occurs when multiple opens are made by the same user
         * to the same article; in this case catch the fatal error caused by FK
         * constraints and just carry on View logged, instead of trying to record
         * both views SA-11038 SA-11134
         */
        try {

            /* It's a valid referrer and $user hasn't Opened this period
             * so add an Open record and increment the cumulative total
             */
            $this->statisticsCumulativeMapper->incrementOpen($article->getId(), $date, $roleType, $roleId, $referrer);
        } catch (PDOException $e) {

            // noop
        }

        /* If the incrementOpen() failed due to contention, still try to record this individual open
         * Its failure should still allow this to try anyway
         */
        try {
            return $this->statisticsMapper->save($open->setOccursAt($date));
        } catch (PDOException $e) {

            // nopo
        }

        return false;
    }


    /**
     * Fetch Cumulative data across the lifetime of an article
     *
     * @param News\Model $article
     * @param array      $audience
     * @return array
     */
    public function fetchCumulative(News\Model $article, array $audience): array
    {
        $pb = $this->statisticsCumulativeMapper->getPredicateBuilder()
                                               ->equals('article_id', $article->getId());

        $statistics = $this->statisticsCumulativeMapper->findByPredicates(
            $pb,
            ['order' => ['date' => 'asc',],]
        );

        $summary = [

            /* First open (a specific alteration for this data set)
             */
            'date'     => $statistics->count() ? $statistics->first()->getDate() : null,

            /* Total number of Opens across all time
             */
            'numOpens' => $this->calculateNumOpens($statistics),

            'numUniqueOpens' => $this->uniqueOpens($article, $audience),

            /* Total number of Comments
             */
            'numComments'    => $this->calculateNumComments($article),

            /* Total number of times this article got Saved For Later
             */
            'numSaved'       => $this->calculateNumSaved($article),
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
        $statistics = $this->statisticsCumulativeMapper->findByColumn(
            'article_id',
            $article->getId(),
            [
            'order' => [
                'date' => 'asc',
            ],
            ]
        );

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
            'date'      => $statistics->count() ? $statistic->getDate() : null,
            'breakdown' => [
                'grid' => $breakdown,
            ],
        ];
    }

    /**
     * Generate placeholder timestamps, superimpose actual data on top of placeholders
     * Find the earliest and latest data points and generate a range between them
     *
     * @param Scale\IScale $strat
     * @param News\Model   $article
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function generatePlaceholders(News\Statistics\Scale\IScale $strat, News\Model $article): array
    {
        $statement = $this->database->prepareQuery(
            "
            SELECT
                ROUND(MIN(nodes.timestamp))/1000 AS min_timestamp,
                ROUND(MAX(nodes.timestamp))/1000 AS max_timestamp
            FROM (
                SELECT
                    {$strat->getSql()}
                    role_type,
                    COUNT(*) AS num
                FROM {$this->statisticsMapper->getTable()}
                WHERE article_id = :articleId
                AND deleted_at IS NULL
                GROUP BY timestamp, role_type
                ORDER BY timestamp ASC
            ) nodes
        "
        );

        $statement->execute(
            [
                'articleId' => $article->getId(),
            ]
        );

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $range = $strat->generateDateRange(
            \Alaress_Date_Format::newFromTimestamp((int)$row['min_timestamp']),
            \Alaress_Date_Format::newFromTimestamp((int)$row['max_timestamp'])
        );

        $data = [];

        foreach (array_merge(Role::getRoleTypes(), ['total']) as $roleType) {
            $data[$roleType] = $range;
        }

        return $data;
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

        $statement = $this->database->prepareQuery(
            "
            SELECT
                {$strat->getSql()}
                role_type,
                COUNT(*) AS num
            FROM {$this->statisticsMapper->getTable()}
            WHERE article_id = :articleId
            AND deleted_at IS NULL
            GROUP BY timestamp, role_type
            ORDER BY role_type, timestamp ASC
        "
        );

        $statement->execute(
            [
                'articleId' => $article->getId(),
            ]
        );

        /* Data container containing placeholder elements
         */
        $data = $this->generatePlaceholders($strat, $article);

        /* There's only one Type now (Open) so we can group by Role Type instead
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $data[$row['role_type']][$row['datetime']] = [
                (int)$row['timestamp'],
                (int)$row['num'],
            ];

            /* Have Total series data in the same format
             */
            $data['total'][$row['datetime']][0] = (int)$row['timestamp'];
            $data['total'][$row['datetime']][1] += (int)$row['num'];
        }

        /* Format it for highcharts to understand
         */
        foreach (array_merge(Role::getRoleTypes(), ['total']) as $roleType) {
            $data[$roleType] = array_values($data[$roleType]);

            /* Sort to eliminate anomalous ordering
             *
             * Sometimes data points are added that don't fall within the prescribed set of keys produced by
             * generateDateRange() and are added to the end of the stack, out of order with everything else
             *
             * First encountered setting the period to be longer than 30 days and using the Month scale
             * option but may occur if we misconfigured something
             */
            usort(
                $data[$roleType],
                function ($a, $b) {
                    return $a[0] > $b[0];
                }
            );
        }

        return $data;
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
     * Calculate the amount of unique visitors to an article
     *
     * @param News\Model $article
     * @param array      $audience
     * @return int
     */
    private function uniqueOpens(News\Model $article, array $audience): int
    {
        if (!count($audience)) {
            return 0;
        }
        $pb = $this->statisticsMapper->getPredicateBuilder();
        $pb->equals('article_id', $article->getId());
        $pb->in('user_id', $audience);

        $query = "SELECT COUNT(DISTINCT(user_id)) as views FROM comms_news_article_statistics
                    WHERE " . $pb->getWhereIntersect();

        $stmt  = $this->database->prepareQuery($query);
        $stmt->execute($pb->getParams());
        return $stmt->fetchColumn();
    }


    /**
     * Calculate number of comments for a given article
     *
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

        $statement->execute(
            [
                'articleId' => $article->getId(),
            ]
        );

        return (int)$statement->fetch(\PDO::FETCH_ASSOC)['numComments'];
    }


    /**
     * Calculate the absolute number of times this article has been saved across all time
     * (Includes records that were soft deleted)
     *
     * @param News\Model $article
     * @return int
     * @throws \Alaress\Database\Exception
     */
    private function calculateNumSaved(News\Model $article): int
    {
        $query = '
            SELECT COUNT(*) AS numSaved
            FROM comms_news_saveforlater cns
            WHERE cns.article_id = :articleId
        ';

        $statement = $this->database->prepareQuery($query);

        $statement->execute(
            [
                'articleId' => $article->getId(),
            ]
        );

        return (int)$statement->fetch(\PDO::FETCH_ASSOC)['numSaved'];
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
}
