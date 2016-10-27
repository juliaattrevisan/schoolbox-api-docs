<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * A mapper for a single file within a student's submission to a due work.
 */
class Mapper extends BaseMapper
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission_file');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('uuid', self::TYPE_STRING);
        $this->uses('editable_inline', self::TYPE_BOOLEAN);

        $this->joins('student_submission_id', FileMapper::class, 'id', null, null, 'submission');
        $this->joins('hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
    }
}
