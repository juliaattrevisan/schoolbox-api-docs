<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\GenericCollection;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method null|Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('moment_evidence');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('moment_id', \Schoolbox\Learning\Moment\Mapper::class, 'id', null, null, 'moment', 'moment');
        $this->uses('type', self::TYPE_STRING, null, 'type');
        $this->joins('hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('filename', self::TYPE_STRING, null, 'filename');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }
}
