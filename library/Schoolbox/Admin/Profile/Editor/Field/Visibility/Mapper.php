<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Field\Visibility;

use Schoolbox\GenericCollection;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method null|Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('profile_field_roletype_visibility');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('profile_field_id', \Schoolbox\Admin\Profile\Editor\Field\Mapper::class, 'id', null, null, 'field', 'field');
        $this->uses('roletype', self::TYPE_STRING, null, 'roleType', 'roleType');
    }
}
