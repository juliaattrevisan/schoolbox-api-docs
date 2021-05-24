<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt\Answer;

use Schoolbox\Mapper as BaseMapper;
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
    protected function mapping()
    {
        $this->hasTable('assess_response');
        $this->hasModel(QuizAnswer::class);
        $this->uses('response_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('attempt_id', QuizAttemptMapper::class, 'attempt_id', null, null, 'attempt', 'attempt');

        // Intentionally does not hydrate the superfluous 'section_id' field
        // TODO 17.0 - drop 'section_id' column from DB and remove this comment

        $this->joins('question_id', QuizQuestionMapper::class, 'question_id', null, null, 'question', 'question');
        $this->uses('response', self::TYPE_STRING, null, 'answer');
        $this->uses('comment', self::TYPE_STRING);
        $this->uses('mark', self::TYPE_DECIMAL);
        $this->uses('automarked_already', self::TYPE_BOOL_ENUM, null, 'autoMarked');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}
