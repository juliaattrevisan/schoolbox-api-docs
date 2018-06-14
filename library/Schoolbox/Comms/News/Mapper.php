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

class Mapper extends \Schoolbox\Mapper
{

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
        $this->joins('moderated_by', UserMapper::class);
        $this->uses('moderated_at', self::TYPE_DATETIME);
        $this->uses('publish_at', self::TYPE_DATETIME);
        $this->uses('expires_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsVia(
            'comms_news_article_tag',
            TopicMapper::class,
            'newsTopicID',
            'tag_id',
            'article_id',
            null,
            null,
            self::COLUMN_JOIN_MULTI,
            'tags'
        );
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('author', 'a')},
                {$this->getJoinColumnString('comms_news_article_tag', 'tags')}
        ";
    }


    /**
     * The Next Pieces
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('comms_news_article_tag', null, 'tags')}
            LEFT JOIN {$this->getJoinSql('author', null, 'a')}
        ";
    }


    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /** @var Model $news */
            $news = $this->hydrate($row);

            if ($news) {
                try {
                    $tag = $this->getJoinMapper('comms_news_article_tag')->hydrate($row, 'tags');

                    if ($tag) {
                        $news->getTags()->add($tag);
                    }

                    /** @var User $author */
                    $author = $this->getJoinMapper('author')->hydrate($row, 'a');

                    if ($author) {
                        $news->setAuthor($author);
                    }
                } catch (\Schoolbox\Mapper\Exception $e) {

                    /* noop - cannot hydrate for some reason
                     */
                }
            }

            $collection->add($news);
        }

        return $collection;
    }
}