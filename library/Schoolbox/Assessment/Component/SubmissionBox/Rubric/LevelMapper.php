<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class LevelMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Level hydrate(array $row, $alias = '', $callback = null)
 * @method Level createModel()
 * @method Level findById($id)
 * @method Collection|Level[] findByColumn($columnName, $columnValue, $options = array())
 * @method Collection|Level[] findByColumnList($columnList, $options = array())
 */
class LevelMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Level());

        $this->hasTable('rubric_level');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'box');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('box', 'b')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('box', null, 'b')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = new Collection();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($r);
            $model->setSubmissionBox($this->hydrateJoin($r, 'box', 'b'));

            $c->add($model);
        }

        return $c;
    }


}
