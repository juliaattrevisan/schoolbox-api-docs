<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\IBuilder;
use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use PDO;
use PDOException;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Folder;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Discussion\Thread;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress_Date_Format;
use UnderflowException;
use UnexpectedValueException;

use function implode;
use function is_array;

/**
 * Class Mapper
 *
 * @package Schoolbox\Comms\News
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper implements Searchable
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
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_article');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('blurb', self::TYPE_STRING);
        $this->joins('author_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->uses('sticky', self::TYPE_BOOLEAN);
        $this->joins('feature_image_hash', Storage\Mapper::class, 'hash', null, null, 'featureImage', 'featureImage');
        $this->joins('discussion_thread_id', Thread\Mapper::class, 'id', null, null, 'discussionThread', 'discussionThread');
        $this->uses('submitted_at', self::TYPE_DATETIME);
        $this->joins('accepted_by', UserMapper::class, 'id', null, null, 'acceptedBy', 'moderator');
        $this->uses('accepted_at', self::TYPE_DATETIME);
        $this->joins('rejected_by', UserMapper::class, 'id', null, null, 'rejectedBy', 'rejector');
        $this->uses('rejected_at', self::TYPE_DATETIME);
        $this->uses('rejected_reason', self::TYPE_STRING);
        $this->uses('publish_at', self::TYPE_DATETIME);
        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsVia(
            'comms_news_article_topic',
            Topic\Mapper::class,
            'newsTopicID',
            'topic_id',
            'article_id',
            'id',
            null,
            self::COLUMN_JOIN_MULTI,
            'topics'
        );

        $this->hasJoin(
            'source',
            [
                'multiple'  => false,
                'mapper'    => Source\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'article_id',
            ]
        );

        $this->hasJoin(
            'files',
            [
                'multiple'  => true,
                'mapper'    => File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'article_id',
            ]
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
                {$this->getJoinColumnString('featureImage.allowedFileExtension', 'afe')},
                {$this->getJoinColumnString('discussionThread', 'dt')},
                {$this->getJoinColumnString('source', 's')}
        ";
    }


    /**
     * The Next Pieces
     *
     * @return string
     * @throws DefinitionException
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
            LEFT JOIN {$this->getJoinSql('discussionThread', null, 'dt', true)}
            LEFT JOIN {$this->getJoinSql('source', null, 's', true)}
        ";
    }


    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws DefinitionException
     * @throws UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $idColumn = $this->getIdentifierColumn();

        /** @var Topic\Mapper $topicMapper */
        $topicMapper = $this->getJoinMapper('comms_news_article_topic');

        /** @var UserMapper $authorMapper */
        $authorMapper = $this->getJoinMapper('author');

        /** @var UserMapper $moderatorMapper */
        $moderatorMapper = $this->getJoinMapper('moderator');

        /** @var UserMapper $rejectorMapper */
        $rejectorMapper = $this->getJoinMapper('rejector');

        /** @var Storage\Mapper $featureImageMapper */
        $featureImageMapper = $this->getJoinMapper('featureImage');

        /** @var MimetypeMapper $mimeTypeMapper */
        $mimeTypeMapper = $this->getJoinMapper('featureImage.allowedFileExtension');

        /** @var Source\Mapper $sourceMapper */
        $sourceMapper = $this->getJoinMapper('source');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* If the article was put into this stack already, just reuse it
             * Otherwise hydrate and store
             */
            if ($collection->offsetExists($row[$idColumn])) {
                $article = $collection->offsetGet($row[$idColumn]);
            } else {
                $article = $this->hydrate($row);
                $collection->setOffset($row[$idColumn], $article);

                // Hydrate 1:1 and 1:0..1 joins
                $article->setTopics($topicMapper->createCollection());
                $article->setAuthor($authorMapper->hydrate($row, 'a'));
                $article->setAcceptedBy($moderatorMapper->hydrate($row, 'm'));
                $article->setRejectedBy($rejectorMapper->hydrate($row, 'r'));

                /* If there's a feature image, bolt that on
                 */
                $featureImage = $featureImageMapper->hydrate($row, 'fi');

                if ($featureImage) {
                    $article->setFeatureImage($featureImage->setMimetype($mimeTypeMapper->hydrate($row, 'afe')));
                }

                /* If there's a discussion thread, bolt that on
                 */
                /** @var Thread\Model|null $discussionThread */
                $discussionThread = $this->hydrateJoin($row, 'discussionThread', 'dt');

                if ($discussionThread) {
                    $article->setDiscussionThread($discussionThread);
                }

                /* If this has event source data, bolt that on
                 */
                $source = $this->hydrateJoin($row, 'source', 's');

                if ($source) {
                    $article->setSource($source);
                }
            }

            /* Attach topics
             */
            $topic = $topicMapper->hydrate($row, 'topics');

            if ($topic) {
                $article->getTopics()->setOffset($topic->getId(), $topic);
            }
        }

        /* Reorder topics sequentially
         */
        foreach ($collection as $article) {
            $article->getTopics()->sortBy('sequence', 'asc', null, true);
        }

        return $collection;
    }

    /**
     * Persist News Article files.
     *
     * @param Model $article
     */
    public function saveFiles(Model $article): void
    {
        $this->cascadeJoin('files', ['article_id' => $article->getId()], $article->getAttachedFiles());
    }


    /**
     * Returns a predicate for getting articles that are awaiting moderation
     *
     * @param string|null $tableAlias If provided, use this alias for this
     *                                mapper's table in the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateDraft(string $tableAlias = null): IBuilder
    {
        $prefix = $this->determinePredicateBuilderPrefix($tableAlias);
        return $this->getPredicateBuilder()
                    ->isNull($prefix . 'submitted_at');
    }


    /**
     * Returns a predicate for getting articles that are rejected
     *
     * @param string|null $tableAlias If provided, use this alias for this
     *                                mapper's table in the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateRejected(string $tableAlias = null): IBuilder
    {
        $prefix = $this->determinePredicateBuilderPrefix($tableAlias);
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'submitted_at')
                    ->notNull($prefix . 'rejected_at');
    }


    /**
     * Returns a predicate for getting articles that are awaiting moderation
     *
     * @param string|null $tableAlias If provided, use this alias for this
     *                                mapper's table in the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateAwaitingModeration(string $tableAlias = null): IBuilder
    {
        $prefix = $this->determinePredicateBuilderPrefix($tableAlias);
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'submitted_at')
                    ->isNull($prefix . 'rejected_at')
                    ->isNull($prefix . 'accepted_at');
    }


    /**
     * Returns a predicate for getting articles that are awaiting publication.
     *
     * Must be:
     * * approved at
     * * publish_at > now
     *
     * @param Alaress_Date_Format|null $now
     * @param string|null              $tableAlias If provided, use this alias
     *                                             for this mapper's table in
     *                                             the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateAwaitingPublication(Alaress_Date_Format $now = null, string $tableAlias = null): IBuilder
    {
        $prefix      = $this->determinePredicateBuilderPrefix($tableAlias);
        $nowDatabase = ($now ?? Alaress_Date_Format::now())->getDatabase();
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'accepted_at')
                    ->greaterThan($prefix . 'publish_at', $nowDatabase);
    }


    /**
     * Returns a predicate for getting articles that are approved
     *
     * @param string|null $tableAlias If provided, use this alias for this
     *                                mapper's table in the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateApproved(string $tableAlias = null): IBuilder
    {
        $prefix = $this->determinePredicateBuilderPrefix($tableAlias);
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'accepted_at');
    }


    /**
     * Returns a predicate for getting articles that are published
     *
     * @param Alaress_Date_Format|null $now
     * @param string|null              $tableAlias If provided, use this alias
     *                                             for this mapper's table in
     *                                             the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicatePublished(Alaress_Date_Format $now = null, string $tableAlias = null): IBuilder
    {
        $prefix      = $this->determinePredicateBuilderPrefix($tableAlias);
        $nowDatabase = ($now ?? Alaress_Date_Format::now())->getDatabase();
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'submitted_at')
                    ->isNull($prefix . 'rejected_at')
                    ->notNull($prefix . 'accepted_at')
                    ->lessThanOrEqualTo($prefix . 'publish_at', $nowDatabase)
                    ->greaterThan($prefix . 'expires_at', $nowDatabase);
    }


    /**
     * Returns a predicate for getting articles that are expired
     *
     * @param Alaress_Date_Format|null $now
     * @param string|null              $tableAlias If provided, use this alias
     *                                             for this mapper's table in
     *                                             the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @see Model::getDiscreteState()
     */
    public function getPredicateExpired(Alaress_Date_Format $now = null, string $tableAlias = null): IBuilder
    {
        $prefix      = $this->determinePredicateBuilderPrefix($tableAlias);
        $nowDatabase = ($now ?? Alaress_Date_Format::now())->getDatabase();
        return $this->getPredicateBuilder()
                    ->notNull($prefix . 'submitted_at')
                    ->isNull($prefix . 'rejected_at')
                    ->notNull($prefix . 'accepted_at')
                    ->lessThanOrEqualTo($prefix . 'publish_at', $nowDatabase)
                    ->lessThan($prefix . 'expires_at', $nowDatabase);
    }


    /**
     * Returns a predicate that will match articles in the specified state.
     *
     * @param int         $state      A bit-mask of atomic states
     * @param string|null $tableAlias If provided, use this alias for this
     *                                mapper's table in the returned predicate.
     * @return Builder
     * @throws InvalidColumnException
     * @throws UnderflowException       If state indicates no articles can ever match
     * @throws InvalidArgumentException If state is invalid
     */
    public function getPredicateForState(int $state, string $tableAlias = null): Builder
    {
        if ($state === Model::STATE_NONE) {
            // We could generate a predicate that matches no articles (eg id=0) but that  would
            // cause a wasted round trip to DB, better to force caller to test and short-circuit in code.
            throw new UnderflowException("No articles can ever match an article with no state");
        }

        if ($state < Model::STATE_NONE || $state > Model::STATE_ALL) {
            throw new InvalidArgumentException(sprintf(
                "State must be between %d and %d, found %d",
                Model::STATE_NONE,
                Model::STATE_ALL,
                $state
            ));
        }

        $pb = $this->getPredicateBuilder();

        if ($state & Model::STATE_DRAFT) {
            $pb->all($this->getPredicateDraft($tableAlias));
        }
        if ($state & Model::STATE_AWAITING_MODERATION) {
            $pb->all($this->getPredicateAwaitingModeration($tableAlias));
        }
        if ($state & Model::STATE_AWAITING_PUBLICATION) {
            $pb->all($this->getPredicateAwaitingPublication(null, $tableAlias));
        }
        if ($state & Model::STATE_REJECTED) {
            $pb->all($this->getPredicateRejected($tableAlias));
        }
        if ($state & Model::STATE_PUBLISHED) {
            $pb->all($this->getPredicatePublished(null, $tableAlias));
        }
        if ($state & Model::STATE_EXPIRED) {
            $pb->all($this->getPredicateExpired(null, $tableAlias));
        }
        return $this->getPredicateBuilder()->any($pb);
    }


    /**
     * Get a list of years and months that have non-deleted articles matching the specified args
     *
     * @param int[]|null $articleIdFilter
     * @param int|null   $state
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws PDOException
     * @throws UnderflowException
     */
    public function findYearsAndMonthsWithArticles(?array $articleIdFilter, ?int $state): array
    {
        if ($articleIdFilter !== null && !count($articleIdFilter)) {
            return [];
        }
        $pb = $this->getPredicateBuilder()->isNull('deleted_at');
        if ($articleIdFilter !== null) {
            $pb->in('id', $articleIdFilter);
        }
        if ($state !== null && $state !== Model::STATE_ALL) {
            $pb->all($this->getPredicateForState($state));
        }

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
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $years[$row['year']][(int)$row['month']] = true;
        }

        return $years;
    }


    /**
     * Return a list of article id's that is authored or approved (If the user
     * has a news moderator permissions) by the user
     *
     * @param User     $user
     * @param int|null $state If provided, find only IDs of articles in this
     *                        state
     * @param bool     $savedOnly
     * @return array
     * @throws InvalidColumnException
     */
    public function findAuthoredOrAcceptedArticleIds(User $user, ?int $state, bool $savedOnly): array
    {
        $joinSaved = SaveForLater\Mapper::getSavedJoin($this->getTable(), $savedOnly);
        $select    = "SELECT `{$this->getTable()}`.id FROM `{$this->getTable()}` {$joinSaved} ";

        $pb = isset($state)
            ? $this->getPredicateForState($state)
            : $this->getPredicateBuilder();
        $pb->isNull('deleted_at');
        $pb    = $pb->all(
            $this->getPredicateBuilder()->any(
                $this->getPredicateBuilder()
                     ->equals('author_id', $user->getId())
                     ->equals('accepted_by', $user->getId())
            )
        );
        $query = $select . ' WHERE ' . implode(' AND ', $pb->getWhere());
        $ids   = $this->database->query($query, array_merge($pb->getParams(), SaveForLater\Mapper::getSavedJoinParams($user, $savedOnly)))->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        return array_map('intval', $ids);
    }


    /**
     * Returns a set of article ids that match the supplied parameters.
     *
     * Each param is combined using intersection. Passing NULL for any param will cause it to be ignored.
     *
     * $limit applies to the number of articles, rather than number of rows due to *:N joins.
     *
     * @param array|null  $availableArticleIds If provided, do not return results outside this set
     * @param string      $topicSlug           Filter to articles that include this topic. 'all' => no filter
     * @param int|null    $offset              If provided, offset results by this many
     * @param int|null    $limit               If provided, limit to this many article ids
     * @param int|null    $publishYear         If provided, filter to articles published in this year
     * @param int|null    $publishMonth        If provided, filter to articles published in this month
     * @param int|null    $temporalState       The article's state. A bit-mask of discrete/atomic states
     * @return int[]
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws PDOException
     * @throws UnderflowException
     * @throws DefinitionException
     */
    public function findArticleIdsByFilters(
        ?array $availableArticleIds,
        string $topicSlug,
        ?int $offset,
        ?int $limit,
        ?int $publishYear,
        ?int $publishMonth,
        ?int $temporalState
    ): array {

        // Short-circuit if searching for an empty set
        if (is_array($availableArticleIds) && !count($availableArticleIds)) {
            return [];
        }

        // Short-circuit if searching for articles without a state (all articles have a state)
        // It's quite valid to generate this value programmatically using boolean logic, it's not
        // an error at this point.
        if ($temporalState === Model::STATE_NONE) {
            return [];
        }

        // Start building predicate - never return deleted articles.
        $pb = $this->getPredicateBuilder()
                   ->isNull('deleted_at');

        if ($temporalState !== null && $temporalState !== Model::STATE_ALL) {
            $pb->all($this->getPredicateForState($temporalState));
        }

        if ($availableArticleIds !== null) {
            $pb->in('id', $availableArticleIds);
        }

        // Build publish date predicate (ensure leading 0s for month)
        $publishMonthString = $publishMonth === null ? null : sprintf('%02d', $publishMonth);
        if ($publishYear !== null && $publishMonthString !== null) {
            $pb->like('publish_at', "{$publishYear}-{$publishMonthString}-%");
        } elseif ($publishYear !== null) {
            $pb->like('publish_at', "{$publishYear}-%");
        } elseif ($publishYear !== null) {
            $pb->like('publish_at', "%-{$publishMonthString}-%");
        }

        /** @var string[] $joins */
        $joins = [];
        /** Required if performing any *:N joins */
        $distinct = false;

        if ($topicSlug !== Mapper::TOPIC_ALL_SLUG) {
            $joins[] = "INNER JOIN {$this->getJoinSql('comms_news_article_topic')}";
            $pb->equals('newsTopic.slug', $topicSlug);
            $distinct = true;
        }

        $distinctClause = $distinct ? 'DISTINCT' : '';
        $join           = implode("\n", $joins);
        $where          = implode("\nAND ", $pb->getWhere());

        /* TODO: Sorting algorithm SA-7097
         */
        $query = "
            SELECT {$distinctClause} {$this->table}.id
            FROM {$this->table}
            {$join}
            WHERE {$where}
            ORDER BY {$this->table}.publish_at DESC, {$this->table}.created_at DESC
        ";

        if ($offset !== null) {
            $query .= "\nOFFSET :offset";
            $pb->putParam('offset', $offset);
        }
        if ($limit !== null) {
            $query .= "\nLIMIT :limit";
            $pb->putParam('limit', $limit);
        }

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param ArticleResolver $articleResolver
     * @param User            $user
     * @param string          $keyword
     * @param int             $offset
     * @param int             $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return Search\Result
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC'): Search\Result
    {
        if (!$limit) {
            $limit = self::DEFAULT_LIMIT;
        }

        // TODO snap this outside the mapper
        /** @var IConnector $database */
        $database = self::getDi()->get('databaseInternal');
        $searcher = new Search\Result($database);
        $searcher->search($articleResolver, $user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }


    /**
     * Performs a check whether the homepage has at least one archived news.
     *
     * @param int $folderId
     *
     * @return bool
     * @throws InvalidColumnException
     * @throws PDOException
     */
    public function checkArchiveForHomepage(int $folderId): bool
    {
        $now = Alaress_Date_Format::now()->getDatabase();

        $pb = $this->getPredicateBuilder()
                   ->lessThan('expires_at', $now)
                   ->lessThan('publish_at', $now)
                   ->notNull('accepted_at')
                   ->isNull('deleted_at');

        $where = implode("\nAND ", $pb->getWhere());

        $query = "
            SELECT COUNT({$this->table}.id)
            FROM {$this->getTable()}
            INNER JOIN comms_news_audience cnau ON {$this->table}.id = cnau.comms_news_article_id
            AND cnau.entity_type = :entity_type
            AND cnau.entity_value = :entity_value
            AND cnau.relationship = :relationship
            WHERE {$where} LIMIT 1
        ";
        $folderParams = [
            'entity_type'  => Folder::getEntityType(),
            'entity_value' => $folderId,
            'relationship' => \Schoolbox\Comms\News\Audience\Model::RELATIONSHIP_SELF,
        ];

        $statement = $this->database->prepare($query);

        $statement->execute(array_merge($pb->getParams(), $folderParams));

        return (bool)$statement->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * @param Role  $role
     * @param array $articleIdsToLimit
     * @return array
     */
    public function findArticleIdsForRole(Role $role, array $articleIdsToLimit): array
    {
        $query  = "
            SELECT `comms_news_article`.id
                FROM `comms_news_article`
                JOIN `comms_news_audience` ON `comms_news_audience`.comms_news_article_id = comms_news_article.id
                                           AND `comms_news_audience`.relationship = :self
                JOIN `perms` ON `perms`.cid = comms_news_audience.entity_value AND `perms`.type > 0
                WHERE `perms`.gid = :roleId
                  AND `comms_news_article`.deleted_at IS NULL
        ";
        $params = [
            'self'   => \Schoolbox\Comms\News\Audience\Model::RELATIONSHIP_SELF,
            'roleId' => $role->getId(),
        ];
        if (!empty($articleIdsToLimit)) {
            $bindings = Builder::getBindings($articleIdsToLimit, 'a');
            $query    .= " AND `comms_news_article`.id IN ({$bindings->getPlaceholder()}) ";
            $params   = array_merge($params, $bindings->getParams());
        }
        $ids = $this->database->query($query, $params)->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    }

    /**
     * Determines the prefix to use in a predicate builder generated by one of
     * the `getPredicate*` methods, based on the `$tableAlias` it receives.
     *
     * @param string|null $tableAlias
     * @return string
     */
    private function determinePredicateBuilderPrefix(?string $tableAlias): string
    {
        return isset($tableAlias) ? "$tableAlias." : '';
    }
}
