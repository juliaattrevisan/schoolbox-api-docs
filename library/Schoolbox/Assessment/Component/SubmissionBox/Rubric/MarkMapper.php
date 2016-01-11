<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDO;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

/**
 * Class MarkMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Mark hydrate(array $row, $alias = '', $callback = null)
 * @method Mark createModel()
 */
class MarkMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('rubric_mark');
        $this->hasPrototype(new Mark());

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('tstamp', self::TYPE_TIMESTAMP, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('mark', self::TYPE_DECIMAL);
        $this->uses('valid', self::TYPE_BOOLEAN);

        $this->joins('student_id', UserMapper::class, 'id', null, null, null, 'user');
        $this->joins('submission_box_id', SubmissionBoxMapper::class, 'id', null, null, null, 'box');
        $this->joins('criterion_id', CriterionMapper::class, 'id', null, null, null, 'criterion');
        $this->joins('descriptor_id', DescriptorMapper::class, 'id', null, null, null, 'descriptor');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('user')},
                    {$this->getJoinColumnString('box')},
                    {$this->getJoinColumnString('criterion')},
                    {$this->getJoinColumnString('descriptor')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('user')}
            LEFT JOIN {$this->getJoinSql('box')}
            LEFT JOIN {$this->getJoinSql('criterion')}
            LEFT JOIN {$this->getJoinSql('descriptor')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Mark[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = new Collection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($r);

            $model->setStudent($this->hydrateJoin($r, 'user'));
            $model->setSubmissionBox($this->hydrateJoin($r, 'box'));
            $model->setCriterion($this->hydrateJoin($r, 'criterion'));
            $model->setDescriptor($this->hydrateJoin($r, 'descriptor'));

            $c->add($model);
        }

        return $c;
    }


}
