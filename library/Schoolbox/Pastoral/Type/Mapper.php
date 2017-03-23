<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Type;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as DocumentClassificationMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('lu_pastoral_type');
        $this->hasModel(Model::class);
        $this->joins('pastoral_document_classification_id', DocumentClassificationMapper::class, 'id', null, null, 'documentClassification', 'documentClassification');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_code', self::TYPE_STRING);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }
}