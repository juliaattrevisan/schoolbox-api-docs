<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Attempt;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;

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
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
    }
}