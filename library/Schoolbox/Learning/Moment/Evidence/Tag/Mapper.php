<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Tag;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for learning moment tags.
 */
class Mapper extends BaseMapper
{
    /**
     * {@inheritDoc}
     */
    protected function mapping(): void
    {
        $this->hasTable('lu_moment_tag');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // No callbacks yet for when a tag is deleted
    }


    /**
     * {@inheritDoc}
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
