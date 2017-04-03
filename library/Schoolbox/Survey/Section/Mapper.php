<?php
/**
 * This is just enough of a mapper to get the survey component rendering. This is in no way complete, and is only being
 * used to read data out of the table. This should be blown to smithereeens(sp?) when Quiz/Surveys are sucked out of
 * the refactoring airlock.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Section;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup column mapping
     */
    protected function mapping()
    {
        $this->hasTable('assess_section');
        $this->hasModel(Model::class);
        $this->uses('section_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('assess_id', SurveyMapper::class, 'id', null, null, 'survey', 'survey');
    }
}