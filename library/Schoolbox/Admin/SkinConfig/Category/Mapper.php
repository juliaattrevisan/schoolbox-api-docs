<?php

declare(strict_types=1);

namespace Schoolbox\Admin\SkinConfig\Category;

use Schoolbox\GenericCollection;

/**
 * Class Mapper
 *
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    protected string $tableName = 'lu_skin_config_category';

    protected function mapping(): void
    {
        $this->hasTable($this->tableName);
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
    }
}
