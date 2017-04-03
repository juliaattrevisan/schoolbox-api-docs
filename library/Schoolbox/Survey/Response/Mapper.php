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

namespace Schoolbox\Survey\Response;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Question\Mapper as SurveyQuestionMapper;
use Schoolbox\Survey\Attempt\Mapper as SurveyAttemptMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup column mapping
     */
    protected function mapping()
    {
        $this->hasTable('assess_response');
        $this->hasModel(Model::class);
        $this->uses('response_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('response', self::TYPE_STRING);
        $this->joins('attempt_id', SurveyAttemptMapper::class, 'id', null, null, 'attempt', 'attempt');
        $this->joins('section_id', SurveyMapper::class, 'id', null, null, 'section', 'section');
        $this->joins('question_id', SurveyQuestionMapper::class, 'id', null, null, 'question', 'question');
    }
}