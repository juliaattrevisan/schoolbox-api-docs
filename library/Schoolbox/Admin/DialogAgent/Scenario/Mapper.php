<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DialogAgent\Scenario;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
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
        $this->hasTable('dialog_agent_scenario');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING, null, 'title');
        $this->uses('description', self::TYPE_STRING, null, 'description');
        $this->uses('prompt_template', self::TYPE_STRING, null, 'promptTemplate');
        $this->uses('type', self::TYPE_STRING, null, 'type');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'field',
            [
                'mapper'    => Field\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'scenario_id',
                'multiple'  => true,
            ]
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

        /** @var Model[] $scenarios */
        $scenarios = [];

        foreach ($statement as $row) {
            if (!isset($scenarios[$row['id']])) {
                /** @var Model $scenario */
                $scenario = $this->hydrate($row);
                $scenario->setFields($scenarioFieldMapper->createCollection());

                $scenarios[$row['id']] = $scenario;
            }

            /* Stack up all the adjoining fields
             */
            $field = $scenarioFieldMapper->hydrate($row, 'field');

            if ($field instanceof Field\Model) {
                $scenarios[$row['id']]->addField($field);
            }
        }

        /* Sort sequentially
         */
        foreach ($scenarios as $scenario) {
            $scenario->getFields()->sortBy('sequence', 'asc');
        }

        return $this->createCollection(array_values($scenarios));
    }
}
