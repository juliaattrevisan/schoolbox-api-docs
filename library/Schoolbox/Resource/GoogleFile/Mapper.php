<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

/**
 * Mapper for google file.
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('google_file');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('google_id', self::TYPE_STRING);
        $this->uses('folder_id', self::TYPE_INTEGER);
        $this->uses('owner_id', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->hasJoin(
            'user',
            [
                'mapper'    => UserMapper::class,
                'this_key'  => 'owner_id',
                'other_key' => 'id',
            ]
        );
        $this->hasJoin(
            'folder',
            [
                'mapper'    => FolderMapper::class,
                'this_key'  => 'folder_id',
                'other_key' => 'id',
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
