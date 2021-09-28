<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use DomainException;
use InvalidArgumentException;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\LTI\Assessment\Model as LtiAssessment;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\Model as Folder;
use UnexpectedValueException;

/**
 * Spawns an appropriate MarkScheme for a given Assessment
 */
class Factory
{

    /**
     * MarkTypes available for Projects
     *
     * @var string[]
     */
    private static $markTypesProject = [
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];


    /**
     * MarkTypes available for DueWorks
     *
     * @var string[]
     */
    private static $markTypesDueWork = [
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_TOTAL   => TotalMarkFractional::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];


    /**
     * MarkTypes available for Tasks
     *
     * @var string[]
     */
    private static $markTypesTask = [
        MarkType::TYPE_COMPLETION => Completion::TITLE,
    ];


    /**
     * MarkTypes available for Quizzes
     *
     * @var string[]
     */
    private static $markTypesQuiz = [
        MarkType::TYPE_TOTAL => TotalMarkFractional::TITLE,
    ];


    /**
     * @var Group\Mapper
     */
    private $gradeLevelGroupMapper;


    /**
     * @var IConfigLoader|null
     */
    private $config;


    /**
     * Constructor
     *
     * @param IConfigLoader|null $config
     */
    public function __construct(?IConfigLoader $config = null)
    {
        $this->config = $config;
    }


    /**
     * @param IAssessable $assessable
     * @return null|IMarkScheme
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws RangeException
     * @throws Mapper\Exception
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

        if ($assessable instanceof LtiAssessment) {
            // TODO replace with a proper mark scheme
            return new None();
        }

        if ($assessable instanceof Project) {
            return $this->spawnForProject($assessable);
        }

        throw new InvalidArgumentException('Unknown IAssessable class: ' . \get_class($assessable));
    }


    /**
     * @param Task $task
     * @throws DomainException if MarkType is invalid for Tasks
     * @return Completion
     */
    protected function spawnForTask(Task $task): ?Completion
    {
        switch ($task->getMarkType()->getId()) {
            case MarkType::TYPE_COMPLETION:
                return new Completion();
            default:
                throw new DomainException('Invalid MarkType for Task: ' . $task->getMarkType()->getName());
        }
    }


    /**
     * @param Quiz $quiz
     * @return TotalMarkFractional
     * @throws DomainException
     */
    protected function spawnForQuiz(Quiz $quiz): ?TotalMarkFractional
    {
        switch ($quiz->getMarkType()->getId()) {
            case MarkType::TYPE_TOTAL:
                return new TotalMarkFractional();
            default:
                throw new DomainException('Invalid MarkType for Quiz: ' . $quiz->getMarkType()->getName());
        }
    }


    /**
     * @param DueWork    $dueWork
     * @param float|null $totalMark
     * @return IMarkScheme
     * @throws RangeException
     * @throws DomainException
     */
    protected function spawnForDueWork(DueWork $dueWork, ?float $totalMark = null)
    {
        switch ($dueWork->getMarkType()->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_TOTAL:
                $maxMark = $totalMark ?? $dueWork->getMaxMark();
                return new TotalMarkFractional($maxMark);
            case MarkType::TYPE_LETTER:
                return new LetterGrade($dueWork->getGradeGroup());
            default:
                throw new DomainException('Invalid MarkType for DueWork: ' . $dueWork->getMarkType()->getName());
        }
    }


    /**
     * Capabilities have their own markscheme. Currently, the type of mark scheme is inferred from the due work they are used on.
     * In the future we might add the ability to choose a different markscheme for each capability.
     *
     * @param IAssessable    $assessable
     * @param float|null $maxMark
     * @return IMarkScheme
     * @throws DomainException
     * @throws RangeException
     */
    public function spawnForCapability(IAssessable $assessable, ?float $maxMark = null): IMarkScheme
    {
        if (null !== $maxMark && $maxMark <= 0) {
            return new None();
        }

        /* Type-enforcement throughout the swamp compels me to keep this as IAssessable $assessable
         * instead of switching it to DueWork $assessable, even though only DueWork gets rubric grids
         */
        if ($assessable instanceof DueWork) {
            /* Mark type never specified; equivalent to TotalMarkFractional,
             * which is to exhibit original behaviour
             */
            if ($assessable->getCapabilityMarkType() === null) {
                return new TotalMarkFractional($maxMark);
            }

            switch ($assessable->getCapabilityMarkType()->getId()) {
                case MarkType::TYPE_PERCENT:
                    return new Percentage();
                case MarkType::TYPE_NONE:
                    return new None();
                case MarkType::TYPE_TOTAL:
                    return new TotalMarkFractional($maxMark);
                case MarkType::TYPE_LETTER:
                    return new LetterGrade($assessable->getCapabilityGradeGroup());
                default:
                    throw new DomainException('Invalid MarkType for DueWork: ' . $assessable->getMarkType()->getName());
            }
        }

        throw new DomainException('$assessable is not an instance of DueWork');
    }


    /**
     * @param Project $project
     * @return IMarkScheme
     * @throws DomainException
     * @throws Mapper\Exception
     */
    protected function spawnForProject(Project $project)
    {
        switch ($project->getMarkType()->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_LETTER:
                // Ensure project's grade group levels are hydrated
                $gradeGroup = $project->getGradeGroup();
                if ($gradeGroup && $gradeGroup->getGradeLevels() === null) {
                    $mapper = Group\Mapper::getInstance();
                    $project->setGradeGroup($mapper->findById($gradeGroup->getId()));
                }
                return new LetterGrade($project->getGradeGroup());
            default:
                throw new DomainException('Invalid MarkType for Project: ' . $project->getMarkType()->getName());
        }
    }


    /**
     * @param IAssessable $assessable
     * @return string[] Indexed by MarkType.id
     * @throws UnexpectedValueException If $assessment class is not registered
     */
    public static function getAvailableMarkTypes(IAssessable $assessable): array
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

        if ($assessable instanceof LtiAssessment) {
            // TODO replace with a proper mark scheme
            return [
                MarkType::TYPE_NONE => None::TITLE,
            ];
        }

        throw new UnexpectedValueException('Unknown IAssessable: ' . get_class($assessable));
    }


    /**
     * @param Folder|null $folder
     * @param bool        $isStaff If false, showOverallGrade will be taken into account
     * @return IMarkScheme
     */
    public function spawnForFolder(?Folder $folder, bool $isStaff): IMarkScheme
    {
        if ($folder && $folder->getYearLevels()->count() > 0) {
            // Get overall grade settings from year level, when all the settings are the same
            $overAllGrades     = array_unique($folder->getYearLevels()->getPropertyValues('overallGrade'));
            $showOverallGrades = array_unique($folder->getYearLevels()->getPropertyValues('showOverallGrade'));

            $overAllGrade     = count($overAllGrades) === 1 ? $overAllGrades[0] : null;
            $showOverallGrade = count($showOverallGrades) === 1 ? $showOverallGrades[0] : null;
        }

        // Otherwise fall back to config
        $overAllGrade     = $overAllGrade ?? $this->config->get('overall_grade');
        $showOverallGrade = $showOverallGrade ?? $this->config->get('show_overall_grade');

        // If the grade is hidden
        if (!$isStaff && !$showOverallGrade) {
            return new None();
        }

        $gradeGroups = $this->getGradeLevelGroupMapper()->findAllForAdminOption();

        /** @var Group\Model|null $gradeGroup */
        $gradeGroup  = $gradeGroups->getBy('id', $overAllGrade);

        if (empty($gradeGroup) || $gradeGroup->getId() === $this->getGradeLevelGroupMapper()::GRADE_GROUP_ID_NONE) {
            return new None();
        } elseif ($gradeGroup->getId() === $this->getGradeLevelGroupMapper()::GRADE_GROUP_ID_PERCENTAGE) {
            return new Percentage();
        }

        return new LetterGrade($gradeGroup);
    }


    /**
     * @return Group\Mapper
     */
    private function getGradeLevelGroupMapper(): Group\Mapper
    {
        if (!$this->gradeLevelGroupMapper) {
            $this->gradeLevelGroupMapper = Group\Mapper::getInstance();
        }

        return $this->gradeLevelGroupMapper;
    }
}
