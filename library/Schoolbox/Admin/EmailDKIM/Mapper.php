<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for Schoolbox Installation Packages
 *
 * @package Schoolbox\Admin\Package
 *
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, $options = [])($identifierValue)
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function mapping(): void
    {
        $this->hasTable('email_dkim');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('domain', self::TYPE_STRING);
        $this->uses('public', self::TYPE_STRING);
        $this->uses('private', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
    }
}
