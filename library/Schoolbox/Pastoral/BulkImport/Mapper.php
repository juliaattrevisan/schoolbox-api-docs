<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Schoolbox\Pastoral\BaseMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for pastoral bulk import records.
 */
class Mapper extends BaseMapper
{
    /**
     * The bulk import shares many fields with the base pastoral mapper.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_bulk_import');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('zip_hash', StorageMapper::class, 'hash', null, null, 'zip', 'zip');
        $this->uses('file_path_template', self::TYPE_STRING);

        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->joins('type_id', PastoralTypeMapper::class, 'id', null, null, 'type', 'type');
        $this->joins('subtype_id', PastoralSubTypeMapper::class, 'id', null, null, 'subtype', 'subtype');

        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body_public', self::TYPE_STRING);
        $this->uses('points', self::TYPE_DECIMAL);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('pastoral_id', Access\Mapper::class, 'access', 'access');
        $this->joinsVia(
            'pastoral_bulk_import_tags',
            PastoralTagMapper::class,
            'id',
            'tag_id',
            'pastoral_bulk_import_id',
            'id',
            null,
            null,
            'tags',
        );
    }
}
