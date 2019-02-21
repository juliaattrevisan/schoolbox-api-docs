<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Comment;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_comment');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('private', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('owner', 'o')},
                        {$this->getJoinColumnString('pastoral', 'p')}
            FROM        {$this->getTable()} 
            INNER JOIN  {$this->getJoinSql('owner', '', 'o', true)}
            LEFT JOIN   {$this->getJoinSql('pastoral', '', 'p', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $owner */
            $owner = $this->hydrateJoin($row, 'owner', 'o');
            $model->setOwner($owner);

            /** @var Pastoral $pastoral */
            $pastoral = $this->hydrateJoin($row, 'pastoral', 'p');

            $model->setPastoral($pastoral);

            $collection->add($model);
        }

        return $collection;
    }
}
