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
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($id = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
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
        $this->joins('current_Step', StepMapper::class, 'uuid', null, null, 'tourStep', 'tourStep');
        $this->uses('url', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING);
    }


    protected function getSelectString()
    {
        $x = "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('tourStep', 't')}
            ";
        
        return $x;
    }


    protected function getFromString()
    {
        $x = "
            FROM      {$this->table} 
            JOIN      {$this->getJoinSql("user",null, "u")}
            JOIN      {$this->getJoinSql("tourStep", null, "t")}
            ";

        return $x;
    }


    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            $user = $this->hydrateJoin($row, "user", "u");
            $model->setUser($user);

            $tourStep = $this->hydrateJoin($row, "tourStep", "t");
            $model->setTourStep($tourStep);

            new \Alaress_Probe($row, $user, $model);
        }

        return $collection;
    }

}
