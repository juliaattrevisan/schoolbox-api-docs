<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Action\Mapper as PastoralActionMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as DocumentClassificationMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_type');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_document_classification_id', DocumentClassificationMapper::class, 'id', null, null, 'documentClassification', 'documentClassification');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('template', self::TYPE_STRING);
        $this->uses('color', self::TYPE_COLOR);
        $this->uses('icon', self::TYPE_STRING);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('visibility_locked', self::TYPE_BOOLEAN);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsReverseMulti('pastoral_type_id', Pastoral\SubType\Mapper::class, 'subTypes', 'subTypes');
        $this->joinsVia('pastoral_type_action', PastoralActionMapper::class, 'id', 'action_id', 'type_id', null, null, null, 'actions');

//        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteCheckPastoralSubtypes']);
//        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteCheckPastoralRecords']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralSubtypes']);
    }


    /**
     * @inheritDoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('subTypes', 'st')},
                   {$this->getJoinColumnString('pastoral_type_action', 'actions')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function getFromString(): string
    {
        return "
            FROM      {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('subTypes', '', 'st')}
            LEFT JOIN {$this->getJoinSql('pastoral_type_action', '', 'actions')}
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

        /** @var Pastoral\SubType\Mapper $subTypeMapper */
        $subTypeMapper = $this->getJoinMapper('subTypes');


        /* Pastoral Actions need to be part of findById() in Admin Lists controller
         * otherwise you lose their relationships when you change a Type's details
         */
        $actionsMapper = $this->getJoinMapper('pastoral_type_action');

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $modelId = $row[$this->getIdentifierColumn()];

            if ($collection->keyExists($modelId)) {
                $model = $collection[$modelId];
            } else {
                $model                = $this->hydrate($row);
                $collection[$modelId] = $model;
            }

            $subType = $subTypeMapper->hydrate($row, 'st');
            if ($subType) {
                $model->getSubTypes()[$subType->getId()] = $subType;
            }

            /* Hydrate pastoral actions, if there are any associated
             */
            $action = $actionsMapper->hydrate($row, 'actions');

            if (null !== $action) {
                $model->getActions()->add($action);
            }
        }
        return $collection;
    }


//    /**
//     * Pre-soft-delete callback to ensure there are no
//     * non-soft-deleted pastoral records with this pastoral type.
//     *
//     * @param Model $pastoralType
//     *
//     * @return bool
//     * @throws UnexpectedValueException If pastoral records exists with this pastoral type
//     */
//    public function softDeleteCheckPastoralRecords(Model $pastoralType)
//    {
//        $pastoralRecordMapper = PastoralMapper::getInstance();
//        $pb                   = $pastoralRecordMapper->getPredicateBuilder();
//        $pb->isNull('deleted_at');
//        $pb->equals('type_id', $pastoralType->getId());
//
//        $countRecords = $pastoralRecordMapper->countByPredicates($pb);
//        if ($countRecords) {
//            throw new UnexpectedValueException(
//                sprintf('Cannot delete PastoralType Model. %d non-deleted pastoral records still linked', $countRecords)
//            );
//        }
//
//        return true;
//    }
//
//
//    /**
//     * Pre-soft-delete callback to ensure there are no non-soft-deleted pastoral
//     * records with one of this record's subtypes.
//     *
//     * @param Model $pastoralType
//     *
//     * @return bool
//     * @throws UnexpectedValueException If pastoral records exists with a
//     *                                  subtype of this pastoral type
//     */
//    public function softDeleteCheckPastoralSubtypes(Model $pastoralType)
//    {
//        $pastoralRecordMapper = PastoralMapper::getInstance();
//        $pb                   = $pastoralRecordMapper->getPredicateBuilder();
//        $pb->isNull('deleted_at');
//        $pb->equals('c.pastoral_type_id', $pastoralType->getId());
//
//        $countRecords = $pastoralRecordMapper->countByPredicates($pb);
//        if ($countRecords) {
//            throw new UnexpectedValueException(
//                sprintf('Cannot delete PastoralType Model. %d non-deleted pastoral records still linked', $countRecords)
//            );
//        }
//
//        return true;
//    }


    /**
     * Deletes any pastoral audiences for this Type
     *
     * @param Model $type
     * @return bool
     */
    protected function deletePastoralAudiences(Model $type): bool
    {
        Pastoral\Audience\Mapper::getInstance()->deleteByColumn('type_id', $type->getId());
        return true;
    }


    /**
     * Deletes any pastoral subtypes for this type.
     *
     * @param Model $type
     * @return bool
     */
    protected function deletePastoralSubtypes(Model $type): bool
    {
        Pastoral\SubType\Mapper::getInstance()->deleteByColumn('pastoral_type_id', $type->getId());
        return true;
    }


    /**
     * Order subtypes secondarily, so that they are fetched in order.
     *
     * @inheritDoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC', 'st.sequence' => 'ASC'];
    }
}
