<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Action;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\SubType\Model as PastoralSubtype;
use Schoolbox\Pastoral\Type\Model as PastoralType;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\Pastoral\Action
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_action');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Find a collection of actions associated with pastoral record
     *
     * @param Pastoral $pastoral
     *
     * @return GenericCollection
     */
    public function findForRecord(Pastoral $pastoral): GenericCollection
    {
        $statement = $this->getDatabase()->prepare("
            {$this->getSelectString()}
            INNER JOIN      `pastoral_record_action` ON `pastoral_record_action`.`action_id` = `{$this->getTable()}`.`id`
            WHERE           `pastoral_record_action`.`pastoral_id` = :pastoralId
            AND             `{$this->getTable()}`.`deleted_at` IS NULL
            ORDER BY        `sequence`
        ");

        $statement->execute(['pastoralId' => $pastoral->getId()]);

        return $this->hydrateSelect($statement);
    }

    /**
     * Find a collection of actions associated with pastoral type
     *
     * @param PastoralType $pastoralType
     *
     * @return GenericCollection
     */
    public function findForType(PastoralType $pastoralType): GenericCollection
    {
        $statement = $this->getDatabase()->prepare("
            {$this->getSelectString()}
            INNER JOIN      `pastoral_type_action` ON `pastoral_type_action`.`action_id` = `{$this->getTable()}`.`id`
            WHERE           `pastoral_type_action`.`type_id` = :pastoralTypeId
            AND             `{$this->getTable()}`.`deleted_at` IS NULL
            ORDER BY        `sequence`
        ");

        $statement->execute(['pastoralTypeId' => $pastoralType->getId()]);

        return $this->hydrateSelect($statement);
    }

    /**
     * Find a collection of actions associated with pastoral subtype
     *
     * @param PastoralSubtype $pastoralSubtype
     *
     * @return GenericCollection
     */
    public function findForSubtype(PastoralSubtype $pastoralSubtype): GenericCollection
    {
        $statement = $this->getDatabase()->prepare("
            {$this->getSelectString()}
            INNER JOIN      `pastoral_type_action` ON `pastoral_type_action`.`action_id` = `{$this->getTable()}`.`id`
            WHERE           `pastoral_type_action`.`subtype_id` = :pastoralSubtypeId
            AND             `{$this->getTable()}`.`deleted_at` IS NULL
            ORDER BY        `sequence`
        ");

        $statement->execute(['pastoralSubtypeId' => $pastoralSubtype->getId()]);

        return $this->hydrateSelect($statement);
    }
}
