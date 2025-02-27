<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\SubType;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\SubType\Model as SubTypeModel;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use PDOStatement;
use Schoolbox\Pastoral\Type\Model as PastoralType;

/**
 * @method Model|null hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($identifierValue)
 * @method GenericCollection|Model[] findAll($options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_subtype');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_type_id', PastoralTypeMapper::class, 'id', null, null, 'pastoralType', 'pastoralType');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('entry_tip', self::TYPE_STRING);
        $this->uses('template', self::TYPE_STRING);
        $this->uses('template_private', self::TYPE_STRING);
        $this->uses('color', self::TYPE_COLOR);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('visibility_locked', self::TYPE_BOOLEAN);
        $this->uses('sentiment', self::TYPE_STRING, self::COLUMN_ENUM, null, PastoralType::SENTIMENT_VALUES);
        $this->uses('default_points', self::TYPE_DECIMAL);
        $this->uses('default_expiry', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsReverseMulti('subtype_id', Pastoral\TypeAction\Mapper::class, 'typeActions', 'typeActions');

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateTypeHierarchy']);

//        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteCheckPastoralRecords']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
    }


    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('pastoralType', 't')},
                   {$this->getJoinColumnString('typeActions', 'typeActions')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function getFromString(): string
    {
        return "
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('pastoralType', '', 't', true)}
            LEFT JOIN {$this->getJoinSql('typeActions', '', 'typeActions')}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        /** @var Pastoral\Type\Mapper $typeMapper */
        $typeMapper = $this->getJoinMapper('pastoralType');

        /* Pastoral typeActions need to be part of findById() in Admin Lists controller
         * otherwise you lose their relationships when you change a Subtype's details
         */
        $typeActionsMapper = $this->getJoinMapper('typeActions');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $modelId = $row[$this->getIdentifierColumn()];

            if ($collection->keyExists($modelId)) {
                $model = $collection[$modelId];
            } else {
                $model                = $this->hydrate($row);
                $collection[$modelId] = $model;
            }

            $type = $typeMapper->hydrate($row, 't');

            /* Hydrate pastoral typeActions, if there are any associated
             */
            if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $typeActions = $typeActionsMapper->findByColumn('subtype_id', $model->getId());
                if ($typeActions->count() > 0) {
                    $model->setTypeActions($typeActions);
                }
            }

            $model->setPastoralType($type);
        }
        return $collection;
    }


    /**
     * If hierarchy of types is changing, ensure pastoral records also reflect the new hierarchy.
     *
     * @param SubTypeModel $subType
     * @param ?SubTypeModel $previous
     * @return void
     */
    protected function updateTypeHierarchy(SubTypeModel $subType, ?SubTypeModel $previous): void
    {
        if ($previous === null) {
            // No previous subtype, so there are no old records to update.
            return;
        }

        if ($subType->getPastoralType()->getId() !== $previous->getPastoralType()->getId()) {
            $this->getDatabase()->query(
                "UPDATE pastoral SET
                type_id = ?
                WHERE subtype_id = ? ",
                [$subType->getPastoralType()->getId(), $subType->getId()]
            );
        }
    }


//    /**
//     * Pre-soft-delete callback to ensure there are no
//     * non-soft-deleted pastoral records with this pastoral subtype.
//     *
//     * @param Model $pastoralSubType
//     *
//     * @return bool
//     * @throws UnexpectedValueException If pastoral records exists with this pastoral subtype
//     */
//    public function softDeleteCheckPastoralRecords(Model $pastoralSubType)
//    {
//        $pastoralRecordMapper = Pastoral\Mapper::getInstance();
//        $pb                   = $pastoralRecordMapper->getPredicateBuilder();
//        $pb->isNull('deleted_at');
//        $pb->equals('subtype_id', $pastoralSubType->getId());
//
//        $countRecords = $pastoralRecordMapper->countByPredicates($pb);
//        if ($countRecords) {
//            $message = 'Cannot delete PastoralSubType Model. %d non-deleted pastoral records still linked';
//            throw new UnexpectedValueException(sprintf($message, $countRecords));
//        }
//
//        return true;
//    }


    /**
     * Deletes any pastoral audiences for this SubType
     *
     * @param Model $subType
     * @return bool
     */
    protected function deletePastoralAudiences(Model $subType): bool
    {
        Pastoral\Audience\Mapper::getInstance()->deleteByColumn('subtype_id', $subType->getId());
        return true;
    }


    /**
     * Order subtypes by their base type first
     *
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['t.sequence' => 'ASC', 'sequence' => 'ASC'];
    }
}
