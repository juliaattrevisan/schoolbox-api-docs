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

    /**
     * @param IAssessable $assessable
     * @return IMarkScheme
     */
    public function spawnMarkSchemeFor(IAssessable $assessable)
    {
        if ($assessable instanceof Task) {
            return $this->spawnForTask($assessable);
        } elseif ($assessable instanceof Quiz) {
            return $this->spawnForQuiz($assessable);
        } elseif ($assessable instanceof DueWork) {
            return $this->spawnForDueWork($assessable);
        } elseif ($assessable instanceof Project) {
            return $this->spawnForProject($assessable);
        } else {
            throw new \InvalidArgumentException('Unknown IAssessable class: ' . get_class($assessable));
        }
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
                return new TotalMarkFractional($quiz->getMaxMark());
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
            case MarkType::TYPE_FREE:
                // This is the ancient free grade mark type.
                // As far as I know it was never migrated, but I still need it to not crash and die.
                return new None();
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
}