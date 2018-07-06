<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\UserMapper;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * Representations of when an article is in your Saved For Later list
     */
    public const SAVED = 1;
    public const UNSAVED = 0;

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_saveforlater');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('article_id', News\Mapper::class, 'id', null, null, 'article', 'article');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Find articles that were saved by $userId
     *
     * @param int $userId
     * @return Collection
     * @throws InvalidColumnException
     */
    public function findForUser(int $userId): Collection
    {
        $ids = $this->findSavedIdsForUser($userId);

        if (\count($ids)) {
            return News\Mapper::getInstance()->findByColumn('id', array_keys($ids), [
                'order' => [
                    'publish_at' => 'asc'
                ]
            ]);
        }

        return new Collection();
    }


    /**
     * Returns an array of (article_id, 1) tuples for saved articles
     * This allows you to quickly figger out if an article was Saved For Later
     * and assign the state to the Save For Later button accordingly
     *
     * @param int $userId
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findSavedIdsForUser(int $userId): array
    {
        $pb = $this->getPredicateBuilder();
        $pb->equals('user_id', $userId);

        $ids = $this->findScalarByPredicates('article_id', $pb);

        return array_fill_keys($ids, self::SAVED);
    }
}
