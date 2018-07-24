<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Database\Predicate\Builder;
use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Comms\News
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * This const represents "all topics", as an int id
     */
    public const TOPIC_ALL_ID = 0;

    /**
     * This const represents "all topics", as a slug
     */
    public const TOPIC_ALL_SLUG = 'all';


    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_article');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('blurb', self::TYPE_STRING);
        $this->joins('feature_image_hash', Storage\Mapper::class, 'hash', null, null, 'featureImage', 'featureImage');
        $this->joins('author_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('accepted_by', UserMapper::class, 'id', null, null, 'acceptedBy', 'moderator');
        $this->uses('accepted_at', self::TYPE_DATETIME);
        $this->joins('rejected_by', UserMapper::class, 'id', null, null, 'rejectedBy', 'rejector');
        $this->uses('rejected_at', self::TYPE_DATETIME);
        $this->uses('rejected_reason', self::TYPE_STRING);
        $this->uses('submitted_at', self::TYPE_DATETIME);
        $this->uses('publish_at', self::TYPE_DATETIME);
        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsVia(
            'comms_news_article_topic',
            TopicMapper::class,
            'newsTopicID',
            'topic_id',
            'article_id',
            'id',
            null,
            self::COLUMN_JOIN_MULTI,
            'topics'
        );
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('author', 'a')},
                {$this->getJoinColumnString('comms_news_article_topic', 'topics')},
                {$this->getJoinColumnString('moderator', 'm')},
                {$this->getJoinColumnString('rejector', 'r')}
        ";
    }


    /**
     * The Next Pieces
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('comms_news_article_topic', null, 'topics')}
            INNER JOIN {$this->getJoinSql('author', null, 'a', true)}
            LEFT JOIN {$this->getJoinSql('moderator', null, 'm', true)}
            LEFT JOIN {$this->getJoinSql('rejector', null, 'r', true)}
        ";
    }


    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function hydrateSelect(\PDOStatement $statement): \Schoolbox\GenericCollection
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $article = $this->hydrate($row);

            if ($article) {
                $topic = $this->getJoinMapper('comms_news_article_topic')->hydrate($row, 'topics');

                if ($topic) {
                    $article->getTopics()->setOffset($topic->getId(), $topic);
                }

                /** @var User $author */
                $author = $this->getJoinMapper('author')->hydrate($row, 'a');

                if ($author) {
                    $article->setAuthor($author);
                }

                /** @var User $moderator */
                $moderator = $this->getJoinMapper('moderator')->hydrate($row, 'm');

                if ($moderator) {
                    $article->setAcceptedBy($moderator);
                }

                /** @var User $rejector */
                $rejector = $this->getJoinMapper('rejector')->hydrate($row, 'r');

                if ($rejector) {
                    $article->setRejectedBy($rejector);
                }
            }

            $collection->setOffset($article->getId(), $article);
        }

        /* Reindex the new topics so they're not in an associative array
         * json-encode results in a different structure otherwise
         */
        foreach ($collection as $current) {
            $current->getTopics()->reindex();
        }

        $collection->reindex();

        return $collection;
    }


    /**
     * Get a list of years that have article(s) in them that could be seen by the user
     *
     * @param array $articleIdsForUser
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findYearsAndMonthsWithArticles(array $articleIdsForUser): array
    {
        /* Dates - must be expired, accepted, published and not deleted
         */
        $pb = $this->getPredicateBuilder()
            ->lessThan('expires_at', \Alaress_Date_Format::now())
            ->notNull('publish_at')
            ->notNull('accepted_at')
            ->isNull('deleted_at');

        $where = implode ("\nAND ", $pb->getWhere());

        /* These are articles that the user could see
         * Don't provide year/month combinations that contain articles, but the user isn't in their audience lol
         */
        $availableArticleIdsSql = implode(', ', $articleIdsForUser);

        $query = "
            SELECT 
                SUBSTRING(publish_at, 1, 4) AS year,
                SUBSTRING(publish_at, 6, 2) AS month
            FROM {$this->getTable()}
            WHERE {$where}
            AND {$this->getIdentifierColumn()} IN ({$availableArticleIdsSql})
            GROUP BY year, month 
            ORDER BY year, month
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'expires_at_0' => \Alaress_Date_Format::now()
        ]);

        $years = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $years[$row['year']][(int)$row['month']] = true;
        }

        return $years;
    }

    /**
     * Find just the ids for the articles in a topic
     * We generally use this for pagination counts where we need a count instead of hydrated things
     *
     * Pass null as $limit to disable it; beware the n-n join on articles-topics
     *
     * Filters to the $availableArticleIds that can be seen
     *
     * @param array    $availableArticleIds
     * @param int      $topicId
     * @param int      $limit
     * @param int      $pageNum
     * @param int|null $year
     * @param int|null $month
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    public function findArticleIdsForTopic(array $availableArticleIds, int $topicId, int $limit = 0, int $pageNum = 0, ?int $year, ?int $month): array
    {
        /* Dates - must be accepted, published and not deleted
         */
        $pb = $this->getPredicateBuilder()
            ->notNull('publish_at')
            ->notNull('accepted_at')
            ->isNull('deleted_at');

        /* If either year or month is specified, we're looking in the news archive
         * Look for expired items matching a certain year/month combination
         */
        $now = \Alaress_Date_Format::now();
        $yearMonth = '';

        if ($year || $month) {
            $paddedMonth = '';

            if ($month) {
                $paddedMonth = str_pad($month, 2, '0', STR_PAD_LEFT);
            }

            $yearMonth = "{$year}-{$paddedMonth}%";

            $pb->lessThan('expires_at', $now);
            $pb->like('publish_at', $yearMonth);
        }
        else {
            $pb->greaterThan('expires_at', $now);
        }

        /* String representation of the WHERE clause
         */
        $where = implode("\nAND ", $pb->getWhere());

        /* Filter to articles that I can see
         */
        if (\count($availableArticleIds)) {
            $availableArticleIdsSql = implode(', ', $availableArticleIds);

            $where .= "\nAND id IN ({$availableArticleIdsSql})";
        }

        /* If $topicId is provided, only get articles for that topic
         * Otherwise get $limit articles from any topic
         */
        $query = "
            SELECT id
            FROM {$this->table}
            WHERE {$where}
        ";

        if ($topicId !== self::TOPIC_ALL_ID) {
            $query = "
                SELECT id
                FROM {$this->table}
                INNER JOIN comms_news_article_topic cnat ON {$this->table}.id = cnat.article_id 
                WHERE cnat.topic_id = :topicId
                AND {$where}                
                GROUP BY id
            ";
        }

        /* TODO: Sorting algorithm
         */
        $query .= '
            ORDER BY publish_at DESC, created_at DESC
        ';

        /* Limits and offsets
         */
        if ($limit) {
            $query .= 'LIMIT :limit';

            if ($pageNum) {
                $query .= ' OFFSET :offset';
            }
        }

        $statement = $this->database->prepare($query);

        /* Because pdo does some micky mouse shenanigans
         */
        if ($topicId !== self::TOPIC_ALL_ID) {
            $statement->bindValue('topicId', $topicId, \PDO::PARAM_INT);
        }

        if ($limit) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);

            if ($pageNum) {
                $statement->bindValue('offset', $limit*($pageNum-1), \PDO::PARAM_INT);
            }
        }

        /* ...wow
         */
        $statement->bindValue('expires_at_0', $now, \PDO::PARAM_STR);

        if ($year || $month) {
            $statement->bindValue('publish_at_0', $yearMonth, \PDO::PARAM_STR);
        }

        /* finally
         */
        $statement->execute();

        $articlesInTopic = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $articlesInTopic[] = $row['id'];
        }

        return $articlesInTopic;
    }
}
