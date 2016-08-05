<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Project;

use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Project
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Defines table, model and column/property mappings.
     */
    protected function mapping()
    {
        $this->hasTable('project');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('weight', self::TYPE_INTEGER);
    }
}
