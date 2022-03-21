<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\GenericCollection;


/**
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection findByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_event');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('detail', self::TYPE_STRING);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('day', self::TYPE_STRING);
        $this->uses('period', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('finish_at', self::TYPE_DATETIME);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('classification', self::TYPE_STRING);
        $this->uses('guid', self::TYPE_STRING);
        $this->uses('source', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
