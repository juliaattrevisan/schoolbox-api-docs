<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question\Solution;

use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;

/**
 * Represents the correct Solution to a Quiz Questions
 * 'Solutions' were formerly known as 'Answers'.
 */
class Mapper extends BaseMapper
{
    protected function mapping()
    {
        // TODO 17.0 - update table name to 'quiz_solution' to disambiguate, then delete this comment
        $this->hasTable('assess_answer');
        $this->hasModel(Model::class);
        $this->uses('answer_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('question_id', QuizQuestionMapper::class, 'question_id', null, null, 'question', 'question');
        $this->uses('sequence', self::TYPE_INTEGER);

        // TODO 17.0 - rename table col name 'answer' to 'solution' to disambiguate, then delete this comment
        $this->uses('answer', self::TYPE_STRING, null, 'solution');

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}