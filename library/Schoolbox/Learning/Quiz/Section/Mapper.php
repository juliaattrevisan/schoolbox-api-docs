<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section;

use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;

/**
 * Represents a set of Questions in a Quiz
 */
class Mapper extends BaseMapper
{
    protected function mapping () {
        $this->hasTable('assess_section');
        $this->hasModel(QuizSection::class);
        $this->hasMap(array(
            'column'     => 'section_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'assess_id',
            'property'   => 'quiz',
            'type'       => self::TYPE_MODEL,
            'join'       => 'assess',
        ));
        $this->hasMap(array(
            'column'     => 'sequence',
            'property'   => 'sequence',
            'type'       => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'random',
            'property'   => 'random',
            'type'       => self::TYPE_BOOLEAN
        ));
        $this->hasMap(array(
            'column'     => 'pool',
            'property'   => 'pool',
            'type'       => self::TYPE_INTEGER
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasJoin('quiz', array(
            'mapper'    => OnlineAssessmentMapper::class,
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteQuestions'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreQuestions'));
    }

    /**
     * Soft deletes all Questions for the specified Section
     *
     * @param QuizSection $section
     */
    public function softDeleteQuestions(QuizSection $section)
    {
        $quizQuestionMapper = QuizQuestionMapper::getInstance();
        $questions = $quizQuestionMapper->findByColumn('section_id', $section->getId());
        $quizQuestionMapper->delete($questions);
    }

    /**
     * Restores all soft-deleted Questions for the specified Section
     *
     * @param QuizSection $section
     */
    public function restoreQuestions(QuizSection $section)
    {
        $quizQuestionMapper = QuizQuestionMapper::getInstance();
        $questions = $quizQuestionMapper->findByColumn('section_id', $section->getId(), ['deleted' => true]);
        $quizQuestionMapper->restoreSoftDeleted($questions);
    }
}