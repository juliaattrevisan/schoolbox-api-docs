<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Quiz\Attempt\Answer;

use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;


/**
 * Represents a Students Answer to a Quiz Questions
 * 'Answers' were formerly known as 'Responses'.
 * Name changed to avoid naming collision with Quiz IResponse
 */
class Mapper extends BaseMapper
{
    protected function mapping () {
        $this->hasTable('assess_response');
        $this->hasModel(QuizAnswer::class);
        $this->hasMap(array(
            'column'     => 'response_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'attempt_id',
            'property'   => 'attempt',
            'type'       => self::TYPE_MODEL,
            'join'       => 'attempt',
        ));
        // Intentionally does not hydrate the superfluous 'section_id' field

        $this->hasMap(array(
            'column'     => 'question_id',
            'property'   => 'question',
            'type'       => self::TYPE_MODEL,
            'join'       => 'question',
        ));
        $this->hasMap(array(
            'column'     => 'response',
            'property'   => 'answer',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'comment',
            'property'   => 'comment',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'mark',
            'property'   => 'mark',
            'type'       => self::TYPE_DECIMAL
        ));
        $this->hasMap(array(
            'column'     => 'automarked_already',
            'property'   => 'automarkedAlready',
            'type'       => self::TYPE_BOOL_ENUM
        ));
        $this->hasMap(array(
            'column'     => 'updated_at',
            'property'   => 'updatedAt',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'updated_at',
            'property'   => 'updatedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasJoin('attempt', array(
            'mapper'    => QuizAttemptMapper::class,
            'this_key'  => 'attempt_id',
            'other_key' => 'attempt_id',
        ));
        $this->hasJoin('question', array(
            'mapper'    => QuizQuestionMapper::class,
            'this_key'  => 'question_id',
            'other_key' => 'question_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }
}