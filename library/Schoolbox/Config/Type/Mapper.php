<?php
/**
 * @author      James Leckie <jleckie@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config\Type;

use Schoolbox\Mapper As BaseMapper;

/**
 * Class Mapper
 * @package Schoolbox\LTI\Provider
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('lu_config_type');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
    }
}
