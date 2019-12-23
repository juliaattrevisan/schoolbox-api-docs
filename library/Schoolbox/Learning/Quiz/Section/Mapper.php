<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section;

use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;

/**
 * Represents a set of Questions in a Quiz
 */
class Mapper extends BaseMapper
{
    public const COL_ASSESS_ID = 'assess_id';

    protected function mapping()
    {
        $this->hasTable('assess_section');
        $this->hasModel(QuizSection::class);
        $this->uses('section_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins(self::COL_ASSESS_ID, QuizMapper::class, 'assess_id', null, null, 'quiz', 'quiz');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('random', self::TYPE_BOOLEAN);
        $this->uses('pool', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteQuestions'));
    }

    /**
     * Soft deletes all Questions for the specified Section
     *
     * @param QuizSection $section
     */
    public function softDeleteQuestions(QuizSection $section)
    {
        $quizQuestionMapper = QuizQuestionMapper::getInstance();
        // Force cascade. However this will also updated the 'deleted_at' field for already deleted items.
        $quizQuestionMapper->deleteByColumn('section_id', $section->getId(), ['deleted' => true]);
    }
}
