<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Comms\News
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * This const represents "all topics"
     */
    public const TOPIC_ALL = 0;

    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
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
     * Find a list of articles that are somehow related to $topicId
     *
     * The n-n relational between articles and topics precludes proper usage of findAll()
     * with a limit - it doesn't count properly
     *
     * @param int $topicId
     * @param int $limit
     * @return \Schoolbox\GenericCollection
     */
    public function findForTopic(int $topicId, int $limit): \Schoolbox\GenericCollection
    {
        $articlesInTopic = [];

        /* If $topicId is provided, only get articles for that topic
         * Otherwise get $limit articles from any topic
         */
        $query = "
            SELECT id
            FROM {$this->table}
            WHERE publish_at IS NOT NULL
            AND accepted_at IS NOT NULL
            AND expires_at > NOW()
            ORDER BY publish_at DESC, created_at DESC
        ";

        if ($topicId !== self::TOPIC_ALL) {
            $query = "
                SELECT id
                FROM {$this->table}
                INNER JOIN comms_news_article_topic cnat ON {$this->table}.id = cnat.article_id 
                WHERE publish_at IS NOT NULL
                AND accepted_at IS NOT NULL
                AND expires_at > NOW()
                AND cnat.topic_id = :topicId
                GROUP BY id
                ORDER BY publish_at DESC, created_at DESC
            ";
        }

        if ($limit) {
            $query .= 'LIMIT :limit';
        }

        $statement = $this->database->prepare($query);

        /* Because pdo does some micky mouse shenanigans
         */
        if ($topicId !== self::TOPIC_ALL) {
            $statement->bindValue('topicId', $topicId, \PDO::PARAM_INT);
        }

        if ($limit) {
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }

        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $articlesInTopic[] = $row['id'];
        }

        /* TODO: Filter $articlesInTopic vs articles that I can see
         */

        return $this->findByColumn('id', $articlesInTopic, [
            'order' => [
                'publish_at' => 'desc',
                'created_at' => 'desc',
            ]
        ]);
    }
}
