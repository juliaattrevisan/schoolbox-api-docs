<?php
/**
 * Content tag mapper class
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Tag;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('content_tag');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('content_type', self::TYPE_STRING);
        $this->uses('content_id', self::TYPE_INTEGER);
        $this->uses('tag_type', self::TYPE_STRING);
        $this->uses('tag_id', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('modified_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
