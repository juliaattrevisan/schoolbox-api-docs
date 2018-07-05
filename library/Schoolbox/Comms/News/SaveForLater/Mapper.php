<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\UserMapper;

class Mapper extends \Schoolbox\Mapper
{

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
     * @param int $userId
     * @return Collection
     * @throws InvalidColumnException
     */
    public function findForUser(int $userId): Collection
    {
        $builder = (new Builder($this->getDatabase(), 'comms_news_saveforlater'))
            ->equals('user_id', $userId);

        $ids = $this->findScalarByPredicates('article_id', $builder);

        if (\count($ids)) {
            return News\Mapper::getInstance()->findByColumn('id', $ids);
        }

        return new Collection();
    }
}
