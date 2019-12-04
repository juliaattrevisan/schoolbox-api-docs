<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use InvalidArgumentException;
use Schoolbox\Comms\News;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * Representations of when an article is in your Saved For Later list
     */
    public const SAVED = 1;
    public const UNSAVED = 0;


    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     */
    protected function mapping(): void
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
     * @inheritDoc
     */
    protected function getSelectString()
    {
        return "
            SELECT DISTINCT {$this->getColumnString('', ['article_id'])}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('article', '', 'a', true)} 
        ";
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
    public function findSavedIdsForUser(int $userId) {
        $pb = $this->getPredicateBuilder();
        $pb->equals('user_id', $userId);
        $pb->isNull('a.rejected_at');
        $pb->isNull('deleted_at');

        $query = $this->getSelectString() . ' WHERE ' . implode(' AND ', $pb->getWhere());

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }
}
