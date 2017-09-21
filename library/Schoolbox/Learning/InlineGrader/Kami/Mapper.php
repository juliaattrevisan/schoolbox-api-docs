<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{

    protected function mapping(): void
    {
        $this->hasTable('student_submission_file_kami');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('student_submission_file_id', SingleFile\Mapper::class, 'id', null, null, null, 'file');
        $this->uses('kami_document_id', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('kami_created_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
