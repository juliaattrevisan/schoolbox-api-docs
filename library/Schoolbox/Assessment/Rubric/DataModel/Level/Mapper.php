<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Level;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class LevelMapper
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method Model findById($id)
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
 * @method Collection|Model[] findByColumnList($columnList, $options = array())
 */
class Mapper extends BaseMapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasModel(Model::class);

        $this->hasTable('rubric_level');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'box');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateBoxIdNotChanged']);
    }


    /**
     * Verify this level belongs to the same box it always belonged to.
     *
     * @param Model $level
     * @return bool
     */
    public function validateBoxIdNotChanged(Model $level): bool
    {
        $this->assertJoinedModelNotChanged($level, 'box', 'Rubric levels may not be transferred between due works');
        return true;
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('box', 'b')}
        ";
    }


    /**
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('box', null, 'b', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($r);
            /** @var SubmissionBox $box */
            $box = $this->hydrateJoin($r, 'box', 'b');
            $model->setSubmissionBox($box);

            $c->add($model);
        }

        return $c;
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
