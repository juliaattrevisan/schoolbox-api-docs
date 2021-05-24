<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report\Dashboard;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Report\Mapper as ReportMapper;
use Schoolbox\Pastoral\Report\Model as Report;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_report_dashboard');
        $this->hasModel(Model::class);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('report_id', ReportMapper::class, 'id', null, null, 'report', 'report');
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
                        {$this->getJoinColumnString('report', 'r')},
                        {$this->getJoinColumnString('user', 'u')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('report', '', 'r', true)}
            INNER JOIN  {$this->getJoinSql('user', '', 'u', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            $model->setUser($user);

            /** @var Report $report */
            $report = $this->hydrateJoin($row, 'report', 'r');
            $model->setReport($report);

            $collection->add($model);
        }

        return $collection;
    }
}
