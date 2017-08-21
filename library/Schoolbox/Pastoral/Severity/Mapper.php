<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Severity;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_severity');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('order', self::TYPE_INTEGER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('dashboard', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteCheckPastoralRecords']);
    }


    /**
     * Pre-soft-delete callback to ensure there are no
     * non-soft-deleted pastoral records with this severity.
     *
     * @param Model $severity
     * @return bool
     * @throws \UnexpectedValueException If pastoral records exists with this severity
     */
    public function softDeleteCheckPastoralRecords(Model $severity)
    {
        $pastoralRecordMapper = Pastoral\Mapper::getInstance();
        $pb                   = $pastoralRecordMapper->getPredicateBuilder();
        $pb->isNull('deleted_at');
        $pb->equals('severity_id', $severity->getId());

        $countRecords = $pastoralRecordMapper->countByPredicates($pb);
        if ($countRecords) {
            throw new \UnexpectedValueException(
                sprintf('Cannot delete Severity Model. %d non-deleted pastoral records still linked', $countRecords)
            );
        }
        return true;
    }
}
