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
use Alaress\Database\Predicate\IBuilder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress_Date_Format;

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
                {$this->getJoinColumnString('rejector', 'r')},
                {$this->getJoinColumnString('featureImage', 'fi')},
                {$this->getJoinColumnString('featureImage.allowedFileExtension', 'afe')}
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
            LEFT JOIN {$this->getJoinSql('featureImage', null, 'fi')}
            LEFT JOIN {$this->getJoinSql('featureImage.allowedFileExtension', 'fi', 'afe', true)}
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

                /** @var Storage\Model $featureImage */
                $featureImage = $this->hydrateJoin($row, 'featureImage', 'fi');

                /** @var \Schoolbox\File\Mimetype $mime */
                $mime         = $this->hydrateJoin($row, 'featureImage.allowedFileExtension', 'afe');

                if ($featureImage) {
                    $article->setFeatureImage($featureImage->setMimetype($mime));
                }
            }

            $collection->setOffset($article->getId(), $article);
        }

        return $collection;
    }


    /**
     * Returns a predicate for getting articles that are awaiting moderation
     *
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateDraft(): IBuilder
    {
        return $this->getPredicateBuilder()
                    ->isNull('submitted_at');
    }


    /**
     * Returns a predicate for getting articles that are rejected
     *
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateRejected(): IBuilder
    {
        return $this->getPredicateBuilder()
                    ->notNull('submitted_at')
                    ->notNull('rejected_at');
    }


    /**
     * Returns a predicate for getting articles that are awaiting moderation
     *
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateAwaitingModeration(): IBuilder
    {
        return $this->getPredicateBuilder()
                    ->notNull('submitted_at')
                    ->isNull('rejected_at')
                    ->isNull('accepted_at');
    }


    /**
     * Returns a predicate for getting articles that are awaiting moderation
     *
     * @param Alaress_Date_Format|null $now
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicatePublished(Alaress_Date_Format $now = null): IBuilder
    {
        $nowDatabase = ($now ?? Alaress_Date_Format::now())->getDatabase();
        return $this->getPredicateBuilder()
                    ->notNull('submitted_at')
                    ->isNull('rejected_at')
                    ->notNull('accepted_at')
                    ->lessThanOrEqualTo('publish_at', $nowDatabase)
                    ->greaterThan('expires_at', $nowDatabase);
    }

    /**
     * Get a list of years that have article(s) that were audienced to a user
     *
     * @param array $articleIdsForUser
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    public function findYearsAndMonthsWithArticles(): array
    {
        /* Dates - must be expired, accepted, published and not deleted
         */
        $now = \Alaress_Date_Format::now()->getDatabase();

        $pb = $this->getPredicateBuilder()
            ->lessThan('expires_at', $now)
            ->lessThan('publish_at', $now)
            ->notNull('accepted_at')
            ->isNull('deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT 
                SUBSTRING(publish_at, 1, 4) AS year,
                SUBSTRING(publish_at, 6, 2) AS month
            FROM {$this->getTable()}
            WHERE {$where}
            GROUP BY year, month
            ORDER BY year DESC, month DESC
        ";

        $statement = $this->database->prepare($query);

        $statement->execute($pb->getParams());

        $years = [];

        /* Convert 06 into just 6, because I'm taking chunks out of a timestamp 2018-06-01 11:22:33.
         * The url is an int. To avoid issues with the leading zero I just force int on it.
         */
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
     * @param int|null $limit
     * @param int|null $pageNum
     * @param int|null $year
     * @param int|null $month
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \DomainException
     */
    public function findArticleIdsForTopic(array $availableArticleIds, int $topicId, ?int $limit, ?int $pageNum, ?int $year, ?int $month): array
    {
        /* This user can't see any articles
         */
        if (!\count($availableArticleIds)) {
            return [];
        }

        /* Invalid combination of year/month params
         */
        if ($month && !$year) {
            throw new \DomainException('Invalid year/month combination');
        }

        /* If either year or month is specified, we're looking in the news archive
         * Look for expired items matching a certain year/month combination
         */
        $now = \Alaress_Date_Format::now()->getDatabase();

        /* Dates - must be accepted, published and not deleted
         */
        $pb = $this->getPredicateBuilder()
            ->lessThan('publish_at', $now)
            ->notNull('accepted_at')
            ->isNull('deleted_at')
            ->in('id', $availableArticleIds);

        if ($year || $month) {
            $paddedMonth = '';

            if ($month) {
                $paddedMonth = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
            }

            $yearMonth = "{$year}-{$paddedMonth}%";

            $pb->like('publish_at', $yearMonth);
        } else {
            $pb->greaterThan('expires_at', $now);
        }

        /* If $topicId is provided, only get articles for that topic
         * Otherwise get $limit articles from any topic
         */
        $join = $groupBy = '';
        if ($topicId !== self::TOPIC_ALL_ID) {
            $join    = "INNER JOIN comms_news_article_topic cnat ON {$this->table}.id = cnat.article_id ";
            $groupBy = 'GROUP BY id';
            $pb->equals('cnat.topic_id', $topicId);
        }

        $where = implode("\nAND ", $pb->getWhere());

        /* TODO: Sorting algorithm SA-7097
         */
        $query = "
            SELECT id
            FROM {$this->table}
            {$join}
            WHERE {$where}
            {$groupBy}
            ORDER BY publish_at DESC, created_at DESC
        ";

        /* Limits and offsets
         * Apply either the $limit provided, or use the system default
         *
         *     $limit === int:  use the value of the int for the number of items per page
         *     $limit === null: use system default for number of items per page
         *     $limit === 0:    explicitly get all the items and disregard pagination ('all' is cast to zero)
         */
        if ($pageNum) {
            if ($limit || $limit === null) {
                $query .= ' LIMIT :limit';
                $query .= ' OFFSET :offset';
            }
        }

        $statement = $this->database->prepare($query);

        /* Bind the pb params
         */
        foreach ($pb->getParams() as $key => $value) {
            $statement->bindValue($key, $value);
        }

        /* Because pdo does some mickey mouse shenanigans
         */
        if ($pageNum) {
            if ($limit || $limit === null) {

                /* Apply either the $limit provided, or use the system default
                 */
                $derivedLimit = $limit;

                if (!$limit) {
                    $derivedLimit = (int)$this->getConfigLoader()->get('search_type_limit');
                }

                $statement->bindValue('limit', $derivedLimit, \PDO::PARAM_INT);

                /* Paginate
                 */
                $statement->bindValue('offset', $derivedLimit*($pageNum-1), \PDO::PARAM_INT);
            }
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
