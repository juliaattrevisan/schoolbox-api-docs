<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('summative_report');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('introduction', self::TYPE_STRING);
        $this->joins('logo_hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'logo', 'logo');
        $this->uses('options', self::TYPE_STRING);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
