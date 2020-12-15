<?php
/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Schoolbox\Mapper as BaseMapper;


/**
 * Mapper for Schoolbox Installation Packages
 *
 * @package Schoolbox\Admin\Package
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
        $this->hasTable('package');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('location', self::TYPE_STRING);
        $this->uses('installed', self::TYPE_BOOLEAN);

    }
}
