<?php
/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Status;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Tour\Step\Mapper as StepMapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findOneByColumnList($columnList, $order = [], $options = [])
 * @package Schoolbox\Tour\Step
 */
class Mapper extends BaseMapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('tour_status');
        $this->hasModel(Model::class);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('tour_uuid', TourMapper::class, 'uuid', null, null, 'tour', 'tour');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }


    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('tour', 't')}
            ";
    }


    protected function getFromString()
    {
        return "
            FROM      {$this->table} 
            JOIN      {$this->getJoinSql('user','', 'u')}
            JOIN      {$this->getJoinSql('tour', '', 't')}
            ";
    }


    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            $user = $this->hydrateJoin($row, "user", "u");
            $model->setUser($user);

            $tour = $this->hydrateJoin($row, "tour", "t");
            $model->setTour($tour);

            $collection->add($model);
        }

        return $collection;
    }

}
