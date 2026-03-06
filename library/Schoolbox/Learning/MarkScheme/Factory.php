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
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
use Schoolbox\Learning\Assessment\Task\Model as Task;
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
    private static array $markTypesProject = [
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];


    /**
     * MarkTypes available for DueWorks
     *
     * @var string[]
     */
    private static array $markTypesDueWork = [
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
    private static array $markTypesTask = [
        MarkType::TYPE_COMPLETION => Completion::TITLE,
    ];


    /**
     * MarkTypes available for Quizzes
     *
     * @var string[]
     */
    private static array $markTypesQuiz = [
        MarkType::TYPE_TOTAL   => TotalMarkFractional::TITLE,
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];


    /**
     * MarkTypes available for LTI assessments: only the Total Mark type is
     * available.
     *
     * @var string[]
     */
    private static array $markTypesLtiAssessment = [
        MarkType::TYPE_TOTAL => TotalMarkFractional::TITLE,
    ];


    /**
     * MarkTypes available for Reporting
     *
     * @var array
     */
    private static array $markTypesReporting = [
        MarkType::TYPE_NONE    => None::TITLE,
        MarkType::TYPE_PERCENT => Percentage::TITLE,
        MarkType::TYPE_TOTAL   => TotalMarkFractional::TITLE,
        MarkType::TYPE_LETTER  => LetterGrade::TITLE
    ];


    /**
     * Lesson Plans are never marked. They don't have feedback or responses.
     *
     * @var string[]
     */
    private static array $markTypesLessonPlan = [];


    /**
     * Dialog Agent conversations are never marked.
     *
     * @var string[]
     */
    private static array $markTypesDialogAgent = [];


    /**
     * @var Group\Mapper|null
     */
    private ?Group\Mapper $gradeLevelGroupMapper = null;


    /**
     * Constructor
     *
     * @param IConfigLoader|null $config
     */
    public function __construct(private ?IConfigLoader $config = null)
    {
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
            return $this->spawnForDueWork($assessable, false);
        }

        if ($assessable instanceof LtiAssessment) {
            return $this->spawnForLtiAssessment($assessable);
        }

        if ($assessable instanceof Project) {
            return $this->spawnForProject($assessable);
        }

        if ($assessable instanceof LessonPlan) {
            return $this->spawnForLessonPlan($assessable);
        }

        if ($assessable instanceof DialogAgent) {
            return $this->spawnForDialogAgent($assessable);
        }

        if ($assessable instanceof Reporting) {
            return $this->spawnForReporting($assessable, false);
        }

        throw new InvalidArgumentException('Unknown IAssessable class: ' . \get_class($assessable));
    }


    /**
     * @param IAssessable $assessable
     * @return null|IMarkScheme
     * @throws DomainException If MarkType is invalid for the assessment
     * @throws InvalidArgumentException If assessment class not registered
     * @throws RangeException
     */
    public function spawnInstructorMarkSchemeFor(IAssessable $assessable): ?IMarkScheme
    {
        // only due work has instructor mark type for now
        // when we eventually add more, we can add more cases here
        if (!($assessable instanceof DueWork || $assessable instanceof Reporting)) {
            throw new InvalidArgumentException('Instructor mark scheme not supported in ' . \get_class($assessable));
        }

        $markType = $assessable->getInstructorMarkType();

        if (null === $markType) {
            return null;
        }

        if ($assessable instanceof DueWorK) {
            return $this->spawnForDueWork($assessable, true);
        }

        return $this->spawnForReporting($assessable, true);
    }


    /**
     * @param Task $task
     * @throws DomainException if MarkType is invalid for Tasks
     * @return Completion|null
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
     * @return IMarkScheme
     * @throws DomainException
     */
    protected function spawnForQuiz(Quiz $quiz): IMarkScheme
    {
        switch ($quiz->getMarkType()->getId()) {
            case MarkType::TYPE_TOTAL:
                return new TotalMarkFractional();
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_LETTER:
                return new LetterGrade($quiz->getGradeGroup());
            default:
                throw new DomainException('Invalid MarkType for Quiz: ' . $quiz->getMarkType()->getName());
        }
    }


    /**
     * @param DueWork    $dueWork
     * @param bool       $isInstructor - Whether to spawn the mark scheme for viewing as an instructor or learner
     * @return IMarkScheme
     * @throws RangeException
     * @throws DomainException
     */
    protected function spawnForDueWork(DueWork $dueWork, bool $isInstructor): IMarkScheme
    {
        /* There are two types of mark types (input and display), this determines which function
         * to use from the DueWork object to get the mark type based on if we need the instructor or learner mark
         */
        $methods = [
            'markType' => [
                'learner' => 'getMarkType',
                'instructor' => 'getInstructorMarkType',
            ],
            'maxMark' => [
                'learner' => 'getMaxMark',
                'instructor' => 'getInstructorMaxMark',
            ],
            'gradeGroup' => [
                'learner' => 'getGradeGroup',
                'instructor' => 'getInstructorGradeGroup',
            ],
        ];

        // The key to the methods array for the user type to use
        $userType = $isInstructor ? 'instructor' : 'learner';

        $markType = $dueWork->{$methods['markType'][$userType]}();
        switch ($markType->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_TOTAL:
                $maxMark = $dueWork->{$methods['maxMark'][$userType]}();
                return new TotalMarkFractional($maxMark);
            case MarkType::TYPE_LETTER:
                return new LetterGrade($dueWork->{$methods['gradeGroup'][$userType]}());
            default:
                throw new DomainException('Invalid MarkType for DueWork: ' . $markType->getName());
        }
    }

    /**
     * Spawn a mark scheme for an LTI assessment.
     *
     * @param LtiAssessment $ltiAssessment
     * @return IMarkScheme
     * @throws RangeException
     */
    protected function spawnForLtiAssessment(LtiAssessment $ltiAssessment): IMarkScheme
    {
        return new TotalMarkFractional($ltiAssessment->getLineItemScoreMaximum());
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
                    $project->setGradeGroup($mapper->findByIdWithFallback($gradeGroup->getId()));
                }
                return new LetterGrade($project->getGradeGroup());
            default:
                throw new DomainException('Invalid MarkType for Project: ' . $project->getMarkType()->getName());
        }
    }


    /**
     * @param LessonPlan $lessonPlan
     * @return null
     */
    protected function spawnForLessonPlan(LessonPlan $lessonPlan)
    {
        return null;
    }


    /**
     * This specifically returns a Marktype for learning analytics
     *
     * @param DialogAgent $dialogAgent
     * @return None
     */
    protected function spawnForDialogAgent(DialogAgent $dialogAgent)
    {
        return new None();
    }


    /**
     * @param Reporting $reporting
     * @param bool $isInstructor - Whether to spawn the mark scheme for viewing as an instructor or learner
     * @return IMarkScheme
     * @throws RangeException
     * @throws DomainException
     */
    protected function spawnForReporting(Reporting $reporting, bool $isInstructor): IMarkScheme
    {
        /* There are two types of mark types (input and display), this determines which function
         * to use from the Reporting object to get the mark type based on if we need the instructor or learner mark
         */
        $methods = [
            'markType' => [
                'learner' => 'getMarkType',
                'instructor' => 'getInstructorMarkType',
            ],
            'maxMark' => [
                'learner' => 'getMaxMark',
                'instructor' => 'getInstructorMaxMark',
            ],
            'gradeGroup' => [
                'learner' => 'getGradeGroup',
                'instructor' => 'getInstructorGradeGroup',
            ],
        ];

        // The key to the methods array for the user type to use
        $userType = $isInstructor ? 'instructor' : 'learner';

        $markType = $reporting->{$methods['markType'][$userType]}();
        switch ($markType->getId()) {
            case MarkType::TYPE_PERCENT:
                return new Percentage();
            case MarkType::TYPE_NONE:
                return new None();
            case MarkType::TYPE_TOTAL:
                $maxMark = $reporting->{$methods['maxMark'][$userType]}();
                return new TotalMarkFractional($maxMark);
            case MarkType::TYPE_LETTER:
                return new LetterGrade($reporting->{$methods['gradeGroup'][$userType]}());
            default:
                throw new DomainException('Invalid MarkType for DueWork: ' . $markType->getName());
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
            return self::$markTypesLtiAssessment;
        }

        if ($assessable instanceof LessonPlan) {
            return self::$markTypesLessonPlan;
        }

        if ($assessable instanceof DialogAgent) {
            return self::$markTypesDialogAgent;
        }

        if ($assessable instanceof Reporting) {
            return self::$markTypesReporting;
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
        if ($folder && $folder->getYearLevels() && $folder->getYearLevels()->count() > 0) {
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
