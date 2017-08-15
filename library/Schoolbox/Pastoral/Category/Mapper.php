<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Category;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;

class Mapper extends BaseMapper
{

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_category');
        $this->hasModel(Model::class);
        $this->joins('pastoral_type_id', PastoralTypeMapper::class, 'id', null, null, 'pastoralType', 'pastoralType');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteCheckPastoralRecords']);
    }


    /**
     * Pre-soft-delete callback to ensure there are no
     * non-soft-deleted pastoral records with this pastoral category.
     *
     * @param Model $pastoralCategory
     * @return bool
     * @throws \UnexpectedValueException If pastoral records exists with this pastoral category
     */
    public function softDeleteCheckPastoralRecords(Model $pastoralCategory)
    {
        $pastoralRecordMapper = Pastoral\Mapper::getInstance();
        $pb                   = $pastoralRecordMapper->getPredicateBuilder();
        $pb->isNull('deleted_at');
        $pb->equals('category_id', $pastoralCategory->getId());

        $countRecords = $pastoralRecordMapper->countByPredicates($pb);
        if ($countRecords) {
            $message = 'Cannot delete PastoralCategory Model. %d non-deleted pastoral records still linked';
            throw new \UnexpectedValueException(sprintf($message, $countRecords));
        }
        return true;
    }
}
