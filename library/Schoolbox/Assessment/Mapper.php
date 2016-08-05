<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment;

use Schoolbox\Mapper as BaseMapper;

/**
 * The base mapper for an assessment.
 *
 * @package Schoolbox\Assessment
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Defines table, model and column/property mappings.
     */
    protected function mapping()
    {
        $this->hasTable('assessment');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('publish_date', self::TYPE_DATETIME);
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);
        $this->uses('weight', self::TYPE_INTEGER);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('project_id', Project\Mapper::getInstance(), null, null, null, 'project');
    }
}
