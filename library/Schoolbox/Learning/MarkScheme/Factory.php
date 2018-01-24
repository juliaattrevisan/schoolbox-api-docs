<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;


/**
 * Spawns an appropriate MarkScheme for a given Assessment
 */
class Factory
{
    /** @var string[] MarkTypes available for Projects */
    private static $markTypesProject = [
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];

    /** @var string[] MarkTypes available for DueWorks */
    private static $markTypesDueWork = [
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_TOTAL   => TotalMarkWholeNumber::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];

    /** @var string[] MarkTypes available for Tasks */
    private static $markTypesTask = [
        MarkType::TYPE_COMPLETION => Completion::TITLE,
    ];

    /** @var string[] MarkTypes available for Quizzes */
    private static $markTypesQuiz = [
        MarkType::TYPE_TOTAL => TotalMarkFractional::TITLE,
    ];

    /**
     * @param IAssessable $assessable
     * @return null|IMarkScheme
     * @throws \DomainException If MarkType is invalid for the assessment
     * @throws \InvalidArgumentException If assessment class not registered
     */
    public function spawnMarkSchemeFor(IAssessable $assessable): ?IMarkScheme
    {
        $markType = $assessable->getMarkType();
        if (null === $markType) {
            return null;
        }
        if ($assessable instanceof Task) {
            return $this->spawnForTask($assessable);
        }
        if ($assessable instanceof Quiz) {
            return $this->spawnForQuiz($assessable);
        }
        if ($assessable instanceof DueWork) {
            return $this->spawnForDueWork($assessable);
        }
        if ($assessable instanceof Project) {
            return $this->spawnForProject($assessable);
        }
        throw new \InvalidArgumentException('Unknown IAssessable class: ' . get_class($assessable));
    }

    /**
     * @param Task $task
     * @throws \DomainException if MarkType is invalid for Tasks
     * @return Completion
     */
    protected function spawnForTask(Task $task)
    {
        switch ($task->getMarkType()->getId()) {
            case MarkType::TYPE_COMPLETION:
                return new Completion();
            default:
                throw new \DomainException("Invalid MarkType for Task: " . $task->getMarkType()->getName());
        }
    }

    /**
     * @param Quiz $quiz
     * @return TotalMarkFractional
     */
    protected function spawnForQuiz(Quiz $quiz)
    {
        switch ($quiz->getMarkType()->getId()) {
            case MarkType::TYPE_TOTAL:
                return new TotalMarkFractional();
            default:
                throw new \DomainException("Invalid MarkType for Quiz: " . $quiz->getMarkType()->getName());
        }
    }

    /**
     * @param DueWork $dueWork
     * @return IMarkScheme
     */
    protected function spawnForDueWork(DueWork $dueWork)
    {
        switch ($dueWork->getMarkType()->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_TOTAL:
                return new TotalMarkWholeNumber($dueWork->getMaxMark());
            case MarkType::TYPE_LETTER:
                return new LetterGrade($dueWork->getGradeGroup());
            default:
                throw new \DomainException("Invalid MarkType for DueWork: " . $dueWork->getMarkType()->getName());
        }
    }

    /**
     * @param Project $project
     * @return IMarkScheme
     */
    protected function spawnForProject(Project $project)
    {
        switch ($project->getMarkType()->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_LETTER:
                return new LetterGrade($project->getGradeGroup());
            default:
                throw new \DomainException("Invalid MarkType for Project: " . $project->getMarkType()->getName());
        }
    }

    /**
     * @param IAssessable $assessable
     * @return string[] Indexed by MarkType.id
     * @throws \UnexpectedValueException If $assessment class is not registered
     */
    public static function getAvailableMarkTypes(IAssessable $assessable)
    {
        if ($assessable instanceof Project) {
            return self::$markTypesProject;
        }
        if ($assessable instanceof DueWork) {
            return self::$markTypesDueWork;
        }
        if ($assessable instanceof Quiz) {
            return self::$markTypesQuiz;
        }
        if ($assessable instanceof Task) {
            return self::$markTypesTask;
        }
        throw new \UnexpectedValueException('Unknown IAssessable: ' . get_class($assessable));
    }
}
