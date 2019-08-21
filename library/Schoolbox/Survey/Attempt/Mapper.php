<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Survey\Attempt;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('assess_attempt');
        $this->hasModel(Model::class);
        $this->uses('attempt_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('assess_id', SurveyMapper::class, 'id', null, null, 'survey', 'survey');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
