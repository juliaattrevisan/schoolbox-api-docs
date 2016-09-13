<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;


use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
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
    public function spawnMarkScheme(IAssessable $assessable)
    {
        $class = get_class($assessable);

        switch ($class) {
            case Task::class:
                /** @var Task $assessable */
                return $this->spawnForTask($assessable);
            case Quiz::class:
                /** @var Quiz $assessable */
                return $this->spawnForQuiz($assessable);
            case DueWork::class:
                /** @var DueWork $assessable */
                return $this->spawnForDueWork($assessable);
            case Project::class:
                /** @var Project $assessable */
                return $this->spawnForProject($assessable);
            default:
                throw new \InvalidArgumentException("Unknown IAssessable: $class");
        }
    }

    /**
     * @param Task $task
     * @return Completion
     */
    protected function spawnForTask(Task $task)
    {
        return new Completion();
    }

    /**
     * @param Quiz $quiz
     * @return TotalMarkFractional
     */
    protected function spawnForQuiz(Quiz $quiz)
    {
        return new TotalMarkFractional($quiz->getMaxMark());
    }

    /**
     * @param DueWork $dueWork
     * @return IMarkScheme
     */
    protected function spawnForDueWork(DueWork $dueWork)
    {
        $markTypeId = $dueWork->getMarkType()->getId();
        switch ($markTypeId) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_TOTAL:
                return new TotalMarkWholeNumber($dueWork->getMaxMark());
            case MarkType::TYPE_LETTER:
                /** @var GradeGroup $gradeGroup */
                $gradeGroup = GradeGroupMapper::getInstance()->findById($dueWork->getGradeGroup());
                return new LetterGrade($gradeGroup);
            default:
                throw new \InvalidArgumentException("Unknown MarkType: $markTypeId");
        }
    }

    /**
     * @param Project $project
     * @return IMarkScheme
     */
    protected function spawnForProject(Project $project)
    {
        $markTypeId = $project->getMarkType()->getId();
        switch ($markTypeId) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_LETTER:
                /** @var GradeGroup $gradeGroup */
                $gradeGroup = GradeGroupMapper::getInstance()->findById($project->getGradeGroup());
                return new LetterGrade($gradeGroup);
            default:
                throw new \InvalidArgumentException("Unknown MarkType: $markTypeId");
        }
    }


}