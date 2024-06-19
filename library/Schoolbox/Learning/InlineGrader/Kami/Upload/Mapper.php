<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Upload;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for Kami uploads.
 *
 * This may relate back to:
 * * student submission files
 * * due work worksheet templates
 * * student submission worksheets
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('kami_upload');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('kami_document_id', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('kami_created_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
