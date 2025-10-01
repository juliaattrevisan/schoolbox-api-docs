<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DifferentiationProfiles;

use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Mapper
 *
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findAll($contents = [])
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('differentiation_profiles_config');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('terminology', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('is_enabled', self::TYPE_BOOLEAN);
        $this->uses('module', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
    }

    /**
     * Example pre-save validation.
     *
     * @param Model $model
     * @return bool
     */
//    public function validateUserId(Model $model): bool
//    {
//        if ($model->getUser()->getId() > 100) {
//            throw new UnexpectedValueException('User cannot have an ID above 100');
//        }
//        return true;
//    }
}
