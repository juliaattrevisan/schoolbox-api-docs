<?php

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig\Subcategory;

use Schoolbox\GenericCollection;
use Schoolbox\Admin\SkinConfig\Category\Mapper as CategoryMapper;

/**
 * Class Mapper
 *
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    protected string $tableName = 'lu_skin_config_subcategory';

    protected function mapping(): void
    {
        $this->hasTable($this->tableName);
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('category_id', CategoryMapper::class, 'id', null, null, 'category', 'category');
        $this->uses('name', self::TYPE_STRING);
    }
}
