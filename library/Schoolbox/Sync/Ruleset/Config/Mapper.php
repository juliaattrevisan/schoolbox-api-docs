<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Ruleset\Config;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for sync ruleset configuration.
 *
 * @package Schoolbox\Sync\Ruleset\Config
 * @method Model findById($id)
 * @method Model createModel()
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
 */
class Mapper extends BaseMapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasModel(Model::class);
        $this->hasTable('ups_config');
        $this->uses('role_type', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('enabled', self::TYPE_BOOLEAN);
    }
}
