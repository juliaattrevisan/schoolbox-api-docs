<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Level;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;

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

        $this->joins(
            'rubric_instance_id',
            \Schoolbox\Assessment\Rubric\Instance\Mapper::class,
            'id',
            null,
            null,
            'rubricInstance',
            'rubricInstance'
        );

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
            SELECT 
                {$this->getColumnString()},
                {$this->getJoinColumnString('rubricInstance', 'ri')}
        ";
    }


    /**
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('rubricInstance', '', 'ri', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);

            /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
            $rubricInstance = $this->hydrateJoin($row, 'rubricInstance', 'ri');

            $model->setRubricInstance($rubricInstance);

            $collection->add($model);
        }

        return $collection;
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
