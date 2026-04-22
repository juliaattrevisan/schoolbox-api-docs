<?php

/**
 * IB Category Mapper
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Category;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\IB\Category
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_ib_category');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            'name' => 'ASC',
        ];
    }
}
