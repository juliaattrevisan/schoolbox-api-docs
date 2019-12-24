<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
    public const COL_QUESTION_ID = 'question_id';

    protected function mapping()
    {
        // TODO 17.0 - update table name to 'quiz_solution' to disambiguate, then delete this comment
        $this->hasTable('assess_answer');
        $this->hasModel(Model::class);
        $this->uses('answer_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins(self::COL_QUESTION_ID, QuizQuestionMapper::class, 'question_id', null, null, 'question', 'question');
        $this->uses('sequence', self::TYPE_INTEGER);

        // TODO 17.0 - rename table col name 'answer' to 'solution' to disambiguate, then delete this comment
        $this->uses('answer', self::TYPE_STRING, null, 'solution');

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
