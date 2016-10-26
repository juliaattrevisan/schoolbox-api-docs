<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Quiz\Attempt;

use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use \Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;

/**
 * Represents a Student's Attempt at a Quiz
 */
class Mapper extends BaseMapper
{
    protected function mapping () {
        $this->hasTable('assess_attempt');
        $this->hasModel(QuizAttempt::class);
        $this->hasMap(array(
            'column'     => 'attempt_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'assess_id',
            'property'   => 'quiz',
            'type'       => self::TYPE_MODEL,
            'join'       => 'quiz',
        ));
        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'user',
            'type'       => self::TYPE_MODEL,
            'join'       => 'user',
        ));
        $this->hasMap(array(
            'column'     => 'start',
            'property'   => 'start',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'finish',
            'property'   => 'finish',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'reviewed_at',
            'property'   => 'reviewedAt',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deletedAt',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasJoin('user', array(
            'mapper'    => UserMapper::class,
            'this_key'  => 'user_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('quiz', array(
            'mapper'    => OnlineAssessmentMapper::class,
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteAnswers'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreAnswers'));
    }

    /**
     * Soft deletes all Answers for the specified Attempt
     *
     * @param QuizAttempt $attempt
     */
    public function softDeleteAnswers(QuizAttempt $attempt)
    {
        $quizAnswerMapper = QuizAnswerMapper::getInstance();
        $answers = $quizAnswerMapper->findByColumn('attempt_id', $attempt->getId());
        $quizAnswerMapper->delete($answers);
    }

    /**
     * Restores all soft-deleted Answers for the specified Attempt
     *
     * @param QuizAttempt $attempt
     */
    public function restoreAnswers(QuizAttempt $attempt)
    {
        $quizAnswerMapper = QuizAnswerMapper::getInstance();
        $answers = $quizAnswerMapper->findByColumn('attempt_id', $attempt->getId(), ['deleted' => true]);
        $quizAnswerMapper->restoreSoftDeleted($answers);
    }
}