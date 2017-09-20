<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Event\Source;

use Schoolbox\Calendar\EventMapper;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     * @throws BaseMapper\Exception If unable to derive an ID for mapped joins
     */
    protected function mapping()
    {
        $this->hasTable('event_source');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('event_id', EventMapper::class, 'id', null, null, 'event', 'event');
        $this->uses('source_name', self::TYPE_STRING);
        $this->uses('guid', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}