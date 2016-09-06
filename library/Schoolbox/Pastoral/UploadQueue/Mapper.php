<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\UploadQueue\Model as PastoralUploadQueueModel;

class Mapper extends BaseMapper
{
    const UPLOAD_STATUS_PENDING = 'Pending';
    const UPLOAD_STATUS_FAILED = 'Failed';
    const UPLOAD_STATUS_COMPLETED = 'Completed';

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_upload_queue');
        $this->hasModel(PastoralUploadQueueModel::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->uses('hash', self::TYPE_STRING);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }
}
