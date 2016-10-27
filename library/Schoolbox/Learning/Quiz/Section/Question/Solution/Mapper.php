<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question\Solution;

use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Model as QuizSolution;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;

/**
 * Represents the correct Solution to a Quiz Questions
 * 'Solutions' were formerly known as 'Answers'.
 */
class Mapper extends BaseMapper
{
    protected function mapping () {
        // TODO - update table name to 'quiz_solution'
        $this->hasTable('assess_answer');
        $this->hasModel(QuizSolution::class);
        $this->hasMap(array(
            'column'     => 'answer_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'question_id',
            'property'   => 'question',
            'type'       => self::TYPE_MODEL,
            'join'       => 'question',
        ));
        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'answer',
            'property'   => 'solution',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasJoin('question', array(
            'mapper'    => QuizQuestionMapper::class,
            'this_key'  => 'question_id',
            'other_key' => 'question_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }
}