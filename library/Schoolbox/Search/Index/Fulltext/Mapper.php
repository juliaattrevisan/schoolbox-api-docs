<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Index\Fulltext;

/**
 * Mapper for the full-text component of search indices.
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('search_index_fulltext');
        $this->hasModel(Model::class);
        $this->uses('identifier', self::TYPE_STRING);
        $this->uses('fulltext_index', self::TYPE_STRING);
    }
}
