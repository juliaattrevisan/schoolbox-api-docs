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
    protected function mapping () {
        $this->hasTable('assess_question');
        $this->hasModel(QuizQuestion::class);
        $this->hasMap(array(
            'column'     => 'question_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'section_id',
            'property'   => 'section',
            'type'       => self::TYPE_MODEL,
            'join'       => 'section',
        ));
        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'type_id',
            'property'   => 'type',
            'type'       => self::TYPE_MODEL,
            'join'       => 'type',
        ));
        $this->hasMap(array(
            'column'     => 'marks',
            'property'   => 'marks',
            'type'       => self::TYPE_DECIMAL,
        ));
        $this->hasMap(array(
            'column'     => 'config',
            'property'   => 'config',
            'type'       => self::TYPE_STRING
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasJoin('section', array(
            'mapper'    => QuizSectionMapper::class,
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));
        $this->hasJoin('type', array(
            'mapper'    => QuizQuestionTypeMapper::class,
            'this_key'  => 'type_id',
            'other_key' => 'type_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');

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