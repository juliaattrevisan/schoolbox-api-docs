<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question;

use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Mapper as QuizQuestionTypeMapper;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;

class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('assess_question');
        $this->hasModel(QuizQuestion::class);
        $this->uses('question_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('section_id', QuizSectionMapper::class, 'section_id', null, null, 'section', 'section');
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->joins('type_id', QuizQuestionTypeMapper::class, 'type_id', null, null, 'type', 'type');
        $this->uses('marks', self::TYPE_DECIMAL);
        $this->uses('config', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteSolution'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreSolution'));
    }

    /**
     * Soft deletes the Solution to the specified Question
     *
     * @param QuizQuestion $question
     */
    public function softDeleteSolution(QuizQuestion $question)
    {
        $quizSolutionMapper = QuizSolutionMapper::getInstance();
        // Don't re-delete deleted leaf-nodes. Unlike non-leaf nodes, these will retain original deletion date.
        $solution = $quizSolutionMapper->findByColumn('question_id', $question->getId());
        $quizSolutionMapper->delete($solution);
    }

    /**
     * Restores all soft-deleted Answers for the specified Attempt
     *
     * @param QuizQuestion $question
     */
    public function restoreSolution(QuizQuestion $question)
    {
        $quizSolutionMapper = QuizSolutionMapper::getInstance();
        $solution = $quizSolutionMapper->findByColumn('question_id', $question->getId(), ['deleted' => true]);
        $quizSolutionMapper->restoreSoftDeleted($solution);
    }
}