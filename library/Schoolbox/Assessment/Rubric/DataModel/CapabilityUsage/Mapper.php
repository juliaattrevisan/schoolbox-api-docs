<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage;

use PDOStatement;
use Schoolbox\Assessment\Rubric\DataModel\Capability;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for usage of a rubric capability on a due work.
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model>| findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_capability_usage');
        $this->hasModel(Model::class);
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('sequence', self::TYPE_INTEGER);

        $this->joins(
            'rubric_capability_id',
            Capability\Mapper::class,
            'id',
            null,
            null,
            'capability',
            'capability'
        );

        $this->joins(
            'rubric_instance_id',
            \Schoolbox\Assessment\Rubric\Instance\Mapper::class,
            'id',
            null,
            null,
            'rubricInstance',
            'rubricInstance'
        );
    }


    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('rubricInstance', 'ri')},
                {$this->getJoinColumnString('capability', 'c')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('rubricInstance', '', 'ri', true)}
            INNER JOIN {$this->getJoinSql('capability', '', 'c', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $usage = $this->hydrate($row);

            /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
            $rubricInstance = $this->hydrateJoin($row, 'rubricInstance', 'ri');

            /** @var Capability\Model $capability */
            $capability = $this->hydrateJoin($row, 'capability', 'c');

            $usage
                ->setRubricInstance($rubricInstance)
                ->setCapability($capability);

            $collection->add($usage);
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
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
