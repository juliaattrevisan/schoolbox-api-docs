<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index;

/**
 * Mapper for search indices.
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('search_index');
        $this->hasModel(Model::class);
        $this->uses('identifier', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('date', self::TYPE_DATETIME);
        $this->uses('relevance', self::TYPE_DECIMAL);
        $this->uses('created_by', self::TYPE_INTEGER);
        $this->uses('folder_id', self::TYPE_INTEGER);
        $this->uses('fulltext_index', self::TYPE_STRING);
        $this->uses('text_index', self::TYPE_STRING);
    }
}
