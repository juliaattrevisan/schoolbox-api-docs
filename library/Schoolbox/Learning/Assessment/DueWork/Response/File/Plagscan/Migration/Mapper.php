<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration;

use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration\Model as PlagscanMigration;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3\Connector;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\ValidFiles;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Mapper extends \Schoolbox\Mapper
{

    /**
     * An instance of the Plagscan Connector
     * which is where files end up
     *
     * @var Connector
     */
    private $connector;

    /**
     * A registry of allowed extension-mime tuples
     *
     * @var ValidFiles
     */
    private $validFiles;

    /**
     * Mapping.
     */
    protected function mapping () {
        $this->hasTable('plagscan_document_migration');
        $this->hasModel(PlagscanMigration::class);
        $this->uses('plagscan_document_record_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('storage_hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
//        $this->uses('storage_migrated_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
//        $this->uses('storage_failed_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
