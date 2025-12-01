<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DialogAgent\Scenario\Field\Context;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('dialog_agent_scenario_field_context');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('scenario_field_id', self::TYPE_INTEGER, null, 'field');
        $this->uses('dialog_agent_id', self::TYPE_INTEGER, null, 'dialogAgent');
        $this->uses('body', self::TYPE_STRING, null, 'body');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'scenario_field_id',
            \Schoolbox\Admin\DialogAgent\Scenario\Field\Mapper::class,
            'id',
            null,
            null,
            'field',
            'field',
        );

        $this->joins(
            'dialog_agent_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper::class,
            'id',
            null,
            null,
            'dialogAgent',
            'dialogAgent',
        );
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('field', 'field')}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('field', '', 'field')}
        ";
    }


    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws DefinitionException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        /** @var \Schoolbox\Admin\DialogAgent\Scenario\Field\Mapper $scenarioFieldMapper */
        $scenarioFieldMapper = $this->getJoinMapper('field');

        /** @var Model[] $fieldContexts */
        $fieldContexts = [];

        foreach ($statement as $row) {
            /** @var Model $fieldContext */
            $fieldContext = $this->hydrate($row);

            /* Stack up all the adjoining fields
             */
            $field = $scenarioFieldMapper->hydrate($row, 'field');

            if ($field instanceof \Schoolbox\Admin\DialogAgent\Scenario\Field\Model) {
                $fieldContext->setField($field);
            }

            $fieldContexts[] = $fieldContext;
        }

        /* Sort sequentially
         */
        return $this->createCollection(array_values($fieldContexts));
    }
}
