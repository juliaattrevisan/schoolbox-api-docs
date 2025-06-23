<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\Templates;

use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('editor_template');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('category_id', self::TYPE_INTEGER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('created_by', self::TYPE_INTEGER);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'updatedAt');
        $this->uses('updated_by', self::TYPE_INTEGER);
    }
}
