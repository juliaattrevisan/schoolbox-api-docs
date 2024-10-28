<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Analytics;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\LearningAnalytics\Rubrics\AllRubrics;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Folder\Log\Model as FolderLog;
use Schoolbox\Resource\Folder\Log\Mapper as FolderLogMapper;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Assessment\Term;

/**
 * Class CohortAnalysis
 *
 * Object that bundles up all the learning analysis you might want to do against a cohort.
 *
 * A COHORT is defined as: a set of participants involved in any unit imported from any one course specification at a specific point in time.
 *
 * @package Schoolbox\Learning\Analytics
 */
class CohortAnalysis
{
    const INCOMPLETE = 0;
    const COMPLETE = 1;

    /**
     * Indexed by unitSpec.id
     *
     * @var Collection|UnitSpecification[]
     */
    private $unitSpecCache;

    /**
     * Folders relevant for retrieving hit counts from
     *
     * @var Collection|Folder[]
     */
    private $classFolderCache;

    /**
     * Indexed by user.id
     *
     * @var float[]
     */
    private $courseGrades = [];

    /**
     * Indexed by assessment.id, then user.id
     * Assessments sorted by sequence, then title.
     * The assessment.id is the Spec.ID if it exists, otherwise it is the Impl.ID
     *
     * @var float[][]
     */
    private $assessmentGrades = [];

    /**
     * Indexed by unitSpec.id, assessment.id, user.id
     *
     * Null means no mark
     *
     * @var float[][][]|null[][][]
     */
    private $grades = [];

    /**
     * Indexed by unitSpec.id, assessment.id, user.id
     * Assessment.ID is from Spec if it exists, Impl otherwise
     *
     * 0 => incomplete, 1 => complete
     * @var int[][][]
     */
    private $completions = [];

    /**
     * @var float[][] indexed by unitSpec.id, then user.id
     */
    private $unitProgress = [];

    /**
     * @var float[] indexed by user.id
     */
    private $courseProgress = [];

    /**
     * Average progress for all students in the course/class
     *
     * @var float
     */
    private $averageCourseProgress;

    /**
     * Most advanced student progress in the course/class
     *
     * @var float
     */
    private $highestCourseProgress;

    /**
     * All students participating in the class/course - indexed by user.id
     *
     * @var Collection|User[] $userCache
     */
    private $userCache;

    /**
     * Aggregated Assessments, indexed by assessment.ID, sorted by sequence, then title
     * Spec ID if it exists, Impl.ID if created in UnitImpl
     *
     * @var Collection|IAssessable[]
     */
    private $assessmentCache;

    /**
     * If non-null, filter results for box-plot of DueWork that does not match this type
     *
     * @var null|WorkType\Model
     */
    private $submissionTypeResultFilter;

    /**
     * Are we filtering from cohort down to class level?
     * @var null|Folder
     */
    private $classFolder;


    /**
     * You need to have FULLY hydrated and linked up your units.
     * This class depends on all unit, assessment, participation, response and feedback references being fully accounted for.
     *
     * @param Collection|IAssessable[] $dueAssessments IAssessable implementations due in selected Term and cohort/class
     * @param null|Folder              $classFolder
     * @param null|WorkType\Model      $resultFilter   If supplied, filter results for box-plot of DueWork that does not match this type
     * @throws \DomainException
     * @todo inject mapper dependencies
     */
    public function __construct(Collection $dueAssessments, Folder $classFolder = null, WorkType\Model $resultFilter = null)
    {
        $this->submissionTypeResultFilter = $resultFilter;
        $this->classFolder                = $classFolder;

        $this->buildCaches($dueAssessments);
        $this->aggregateAssessments($dueAssessments);

        $this->calculateCompletionProgress();
        $this->calculateNormalisedMarks();
    }


    /**
     * Copies all assessments to a cache, indexed by assessment.id, so we can grab retrieve O(1)
     *
     * @param Collection|IAssessable[] $dueAssessments
     */
    protected function buildCaches(Collection $dueAssessments)
    {
        $this->assessmentCache   = $dueAssessments->emptyClone();
        $this->userCache         = UserMapper::getInstance()->createCollection();
        $this->classFolderCache  = FolderMapper::getInstance()->createCollection();
        $this->unitSpecCache     = UnitSpecificationMapper::getInstance()->createCollection();

        foreach ($dueAssessments as $assessmentImpl) {
            // User Cache
            foreach ($assessmentImpl->getAllParticipants() as $participant) {
                $user                            = $participant->getUser();
                $this->userCache[$user->getId()] = $user;
            }

            // Assessment Cache
            $this->assessmentCache[$assessmentImpl->getId()] = $assessmentImpl;
            $assessmentSpec = $assessmentImpl->getSpecificationAssessment();
            if ($assessmentSpec) {
                $this->assessmentCache[$assessmentSpec->getId()] = $assessmentSpec;
            }

            // Class Folder Cache
            $folder = $assessmentImpl->getBaseContext()->getFolder();
            $this->classFolderCache->setOffset($folder->getId(), $folder);

            // UnitSpec Cache
            /** @var UnitImplementation $unitImpl */
            $unitImpl = $assessmentImpl->getBaseContext();
            $unitSpec = $unitImpl->getSpecification();
            $this->unitSpecCache[$unitSpec->getId()] = $unitSpec;
        }

        // Sort the Assessments by sequence, then title
        $this->assessmentCache->sortByCallback([$this, 'compareAssessments'])->indexBy('id');
    }



    /**
     * Calculate the normalised marks for:
     * * each student for the course/class
     * * each student per aggregated assessment
     *
     * Ignore:
     * * Tasks
     * * Assessments with zero-weight
     * * Assessments with markType = 'None'
     */
    protected function calculateNormalisedMarks()
    {
        /**
         * Sets of Assessment + normMark, grouped by user.id
         *
         * @var mixed[][] $involvedAssessments
         */
        $involvedAssessments = [];

        foreach ($this->grades as $unitSpecId => $assessmentGrades) {
            foreach ($assessmentGrades as $assessmentId => $studentGrades) {
                // Should we exclude this assessment from report ?
                $assessment = $this->assessmentCache[$assessmentId];
                if ($this->isExcludedForGrades($assessment)) {
                    continue;
                }

                $this->assessmentGrades[$assessmentId] = [];

                foreach ($studentGrades as $userId => $grade) {
                    if (null !== $grade) {
                        $this->assessmentGrades[$assessmentId][$userId] = $grade;
                        $involvedAssessments[$userId][] = [$assessment, $grade];
                    }
                }
            }
        }

        // Ensure assessment grades are in same order as assessment cache
        uksort($this->assessmentGrades, function ($assessmentId1, $assessmentId2) {
            /** @var IAssessable $assessment1 */
            $assessment1 = $this->assessmentCache[$assessmentId1];
            /** @var IAssessable $assessment1 */
            $assessment2 = $this->assessmentCache[$assessmentId2];
            return $this->compareAssessments($assessment1, $assessment2);
        });

        // Calculate student weighted average mark
        /** @var array $assessmentGradePairs */
        foreach ($involvedAssessments as $userId => $assessmentGradePairs) {
            $weightedSum = 0;
            $totalWeight = 0;

            /** @var IAssessable $assessment */
            foreach ($assessmentGradePairs as [$assessment, $normMark]) {
                $weight = $assessment->getWeight();

                $weightedSum += $weight * $normMark;
                $totalWeight += $weight;
            }

            /* Weights are floats now
             */
            if ((float)0 !== $totalWeight) {
                $this->courseGrades[$userId] = $weightedSum / $totalWeight;
            }
        }
    }


    /**
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    public function compareAssessments(IAssessable $left, IAssessable $right)
    {
        if ($left->getSequence() < $right->getSequence()) {
            return -1;
        }

        if ($left->getSequence() > $right->getSequence()) {
            return +1;
        }

        return strcasecmp($left->getTitle(), $right->getTitle());
    }


    /**
     * Calculate student completion progress for course and per unit
     *
     * Includes:
     * * all Assessment types (including Tasks)
     * * Assessments with weight of 0
     * * Assessments with MarkType of None
     *
     * @throws \DomainException
     */
    protected function calculateCompletionProgress()
    {
        /** @var int[] $countCourseAssessmentsInvolved indexed by user.id */
        $countCourseAssessmentsInvolved = [];

        /** @var int[] $countCourseAssessmentsComplete indexed by user.id */
        $countCourseAssessmentsComplete = [];

        foreach ($this->completions as $unitSpecId => $assessmentCompletions) {
            $this->unitProgress[$unitSpecId] = [];

            /** @var int[] $countUnitAssessmentsInvolved indexed by user.id */
            $countUnitAssessmentsInvolved = [];

            /** @var int[] $countUnitAssessmentsComplete indexed by user.id */
            $countUnitAssessmentsComplete = [];

            foreach ($assessmentCompletions as $assessmentId => $studentCompletions) {
                foreach ($studentCompletions as $userId => $completion) {
                    $countUnitAssessmentsInvolved[$userId]   ??= 0;
                    $countCourseAssessmentsInvolved[$userId] ??= 0;
                    $countUnitAssessmentsComplete[$userId]   ??= 0;
                    $countCourseAssessmentsComplete[$userId] ??= 0;

                    $countUnitAssessmentsInvolved[$userId]++;
                    $countCourseAssessmentsInvolved[$userId]++;

                    // Has the participant completed this assessment?
                    if (self::COMPLETE === $completion) {
                        $countUnitAssessmentsComplete[$userId]++;
                        $countCourseAssessmentsComplete[$userId]++;
                    }
                }
            }

            // Calculate each student's completion for this unitSpec
            foreach ($countUnitAssessmentsInvolved as $userId => $countInvolved) {
                $countComplete                            = $countUnitAssessmentsComplete[$userId];
                $this->unitProgress[$unitSpecId][$userId] = 100 * $countComplete / $countInvolved;
            }
        }

        // Calculate each student's course progress, and average student course progress
        $sumCourseProgress     = 0;
        $this->highestCourseProgress = 0;

        foreach ($countCourseAssessmentsInvolved as $userId => $countInvolved) {
            $countComplete                 = $countCourseAssessmentsComplete[$userId];
            $courseProgress                = 100 * $countComplete / $countInvolved;
            $this->courseProgress[$userId] = $courseProgress;

            $sumCourseProgress += $courseProgress;
            $this->highestCourseProgress = max($this->highestCourseProgress, $courseProgress);
        }

        // Protect from DBZ errors (eg if external is down)
        $denominator = count($this->courseProgress);

        $this->averageCourseProgress = 0 === $denominator ? 0 : $sumCourseProgress / $denominator;
    }


    /**
     * Aggregates student completions and grades.
     *
     * If AssessmentImpl imported from AssessmentSpec, aggregate by AssessmentSpec
     * If AssessmentImpl created in UnitImpl then index by AssessmentImpl.ID (no aggregation)
     *
     * If(class)
     * * copy non-imported Assessments
     * * use assessment.weight from AssessmentImpl
     * * student mark = student mark
     * Else(cohort)
     * * ignore non-imported Assessments
     * * use assessment.weight from AssessmentSpec
     * * student mark = max max from non-zero-weight implementation
     *
     * @param Collection|IAssessable[] $dueAssessments
     * @throws \DomainException
     */
    protected function aggregateAssessments(Collection $dueAssessments)
    {
        foreach ($dueAssessments as $assessmentImpl) {

            /** @var UnitImplementation $unitImpl */
            $unitImpl   = $assessmentImpl->getBaseContext();
            $unitSpec   = $unitImpl->getSpecification();
            $unitSpecId = $unitSpec->getId();

            // Was this AssessmentImpl created in UnitImpl (not imported from Spec), and we are looking at cohort (not class) ?
            $assessmentSpec = $assessmentImpl->getSpecificationAssessment();
            if (!$assessmentSpec && null === $this->classFolder) {
                // Ignore non-imported assessments when looking at cohort
                continue;
            }

            // First time we've encountered this unitSpec ?
            $this->completions[$unitSpecId] ??= [];
            $this->grades[$unitSpecId]      ??= [];

            // If AssessmentSpec if at Cohort level, AssessmentImpl if at Class level
            $assessmentId = null === $this->classFolder ? $assessmentSpec->getId() : $assessmentImpl->getId();

            // First time we've encountered this Assessment (or any of its implementations)?
            $this->completions[$unitSpecId][$assessmentId] ??= [];
            $this->grades[$unitSpecId][$assessmentId]      ??= [];


            foreach ($assessmentImpl->getAllParticipants() as $participant) {
                $userId = $participant->getUser()->getId();

                // First time we've seen this user doing this assessment?
                if (!isset($this->completions[$unitSpecId][$assessmentId][$userId])) {
                    $this->completions[$unitSpecId][$assessmentId][$userId] = self::INCOMPLETE;
                }
                if (!isset($this->grades[$unitSpecId][$assessmentId][$userId])) {
                    $this->grades[$unitSpecId][$assessmentId][$userId] = null;
                }

                // Have we not yet concluded the user has completed the assessment ?
                if (self::INCOMPLETE === $this->completions[$unitSpecId][$assessmentId][$userId]) {
                    $this->completions[$unitSpecId][$assessmentId][$userId] = $participant->isResponseRequired() ? self::INCOMPLETE : self::COMPLETE;
                }

                $normMark = $participant->getNormalisedMark(true);
                // Does user not yet have a mark, or this mark is higher ?
                if (null === $this->grades[$unitSpecId][$assessmentId][$userId] || $normMark > $this->grades[$unitSpecId][$assessmentId][$userId]) {
                    $this->grades[$unitSpecId][$assessmentId][$userId] = $normMark;
                }
            }
        }
    }



    /**
     * Returns:
     *   - the progress of the furthest-completed student, and
     *   - the proportion of activities completed by ALL participants in unit SPECIFICATION
     * indexed by unit SPECIFICATION.
     *
     * @return mixed[] Indexed by UnitSpec.ID
     * @var UnitSpecification return['unit']
     * @var float return['mostAdvancedStudent']
     * @var float return['classCompletions']
     */
    public function getProgressByUnit()
    {
        $progress = [];

        foreach ($this->unitProgress as $unitSpecId => $studentProgressions) {
            $maxProgress   = 0;
            $sumProgress   = 0;
            $countStudents = 0;
            foreach ($studentProgressions as $userId => $studentProgress) {
                $countStudents++;
                $sumProgress += $studentProgress;
                $maxProgress = max($maxProgress, $studentProgress);
            }
            $progress[$unitSpecId] = [
                'unit'                => $this->unitSpecCache[$unitSpecId],
                'mostAdvancedStudent' => $maxProgress,
                'classCompletions'    => 0 === $countStudents ? 0 : $sumProgress / $countStudents,
            ];
        }

        return $progress;
    }


    /**
     * Get all students participating in the course/class, indexed by user.id
     *
     * @return GenericCollection|User[]
     */
    public function getParticipatingStudents()
    {
        return $this->userCache;
    }


    /**
     * This is a flattening of getProgressByUnit()
     *
     * Returns two data: as percentages;
     *   - the progress of the furthest-completed participant (fewest activities remaining)
     *   - proportion of activities completed by ALL participants
     *
     * @return float[]
     * @var float return['mostAdvancedStudent']
     * @var float return['classCompletions']
     */
    public function getOverallProgress()
    {
        return [
            'mostAdvancedStudent' => $this->highestCourseProgress,
            'classCompletions'    => $this->averageCourseProgress,
        ];
    }


    /**
     * Returns the IDs and grades of all participants WHO HAVE FEEDBACK DATA in this analysis, indexed by user ID and sorted descending by computed grades.
     * Activities with weight of zero or marktype none are excluded from analysis.
     *
     * @param int $sort
     * @return mixed[][] Sets of User + normMark pairs, grouped by user.id
     */
    public function getCohortRankings($sort = SORT_DESC)
    {
        $courseGrades = $this->courseGrades;

        if (SORT_DESC === $sort) {
            arsort($courseGrades);
        } else {
            asort($courseGrades);
        }

        $userAndAverage = [];
        foreach ($courseGrades as $userId => $normMark) {
            $userAndAverage[$userId] = [
                'user'    => $this->userCache[$userId],
                'average' => $normMark,
            ];
        }

        return $userAndAverage;
    }


    /**
     * Gets a list of Assessments to render in the 'Results' box-plot
     * Respects SubmissionType filtering
     *
     * @param bool $filterBySubmissionType
     * @return Collection|IAssessable[]
     */
    public function getGradableAssessments($filterBySubmissionType)
    {
        $filteredAssessments = $this->assessmentCache->emptyClone();

        foreach ($this->assessmentGrades as $assessmentId => $studentGrades) {
            $assessment = $this->assessmentCache[$assessmentId];
            if (!$filterBySubmissionType || !$this->isExcludedBySubmissionTypeFilter($assessment)) {
                $filteredAssessments[$assessment->getId()] = $assessment;
            }
        }
        return $filteredAssessments;
    }

    public function getWeightedAssessments(): Collection
    {
        $weightedAssessments = $this->assessmentCache->emptyClone();
        foreach ($this->assessmentGrades as $assessmentId => $studentGrades) {
            $assessment = $this->assessmentCache[$assessmentId];
            if ($assessment->getWeight() > 0) {
                $weightedAssessments[$assessment->getId()] = $assessment;
            }
        }
        return $weightedAssessments;
    }

    /**
     * Gets the student marks to display in the 'Results' box-plot.
     * Respects submission-type filtering
     *
     * @param bool $filterBySubmissionType
     * @return mixed[][]
     * @var mixed[] $results [$assessmentId]
     * @var User    $results [$assessmentId][$userId]['user']
     * @var float   $results [$assessmentId][$userId]['grade']
     */
    public function getAssessmentMarks($filterBySubmissionType)
    {
        $results = [];

        foreach ($this->assessmentGrades as $assessmentId => $studentGrades) {
            $assessment = $this->assessmentCache[$assessmentId];

            if (!$filterBySubmissionType || !$this->isExcludedBySubmissionTypeFilter($assessment)) {
                $results[$assessmentId] = [];
                foreach ($studentGrades as $userId => $normMark) {
                    $results[$assessmentId][$userId] = [
                        'user'  => $this->userCache[$userId],
                        'grade' => $normMark,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Should we exclude this assessment from Grades ?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function isExcludedForGrades(IAssessable $assessment)
    {
        return $assessment instanceof Task || $assessment instanceof LessonPlan || MarkType::SLUG_NONE === $assessment->getMarkType()->getSlug();
    }


    /**
     * Should we not include this Assessment in the 'Results' box-plot
     * because it does not match the submission-type filter ?
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function isExcludedBySubmissionTypeFilter(IAssessable $assessment)
    {
        // Exclude if we are filtering results, and this assessment does not match the filter.
        return $this->submissionTypeResultFilter &&  !($assessment->getWorkType() && $assessment->getWorkType()->getId() === $this->submissionTypeResultFilter->getId());
    }


    /**
     * Returns five-point grade data from unit implementations for all participants, indexed by rubric indicator
     * Applicable to the rubric box-and-whisker graph
     *
     * (Cuts the same data as getCohortGradesByAssessment() but by indicators instead of assessment.)
     *
     * @param Collection|UnitImplementation[] $unitImplementations
     * @param Term\Model                      $term
     * @return array
     */
    public function getCohortGradesByIndicator(Collection $unitImplementations, Term\Model $term)
    {
        // exploit old class
        return (new AllRubrics($unitImplementations, $term))->getRubricData();
    }


    /**
     * Returns all participants in the cohort
     * Includes data for all time, as the DB only stores time of last update, not when hit occurred.
     *
     * @throws MapperException
     * @return array Indexed by User.ID
     * @var User  return[user.id]['user']
     * @var int   return[user.id]['hits']
     * @var float return[user.id]['average']
     */
    public function getEffort()
    {
        $tally     = [];
        $folderIds = $this->classFolderCache->getPropertyValues('id');
        $userIds   = array_keys($this->courseGrades);

        /** @var Collection|Collection[]|FolderLog[][] $accessLogs */
        $accessLogs = FolderLogMapper::getInstance()->findByColumnList([
            'user_id'   => $userIds,
            'folder_id' => $folderIds,
        ])->groupByCallback(function (FolderLog $log) {
            return $log->getUser()->getId();
        });

        foreach ($this->courseGrades as $userId => $normMark) {
            $tally[$userId] = [
                'user'    => $this->userCache[$userId],
                'hits'    => empty($accessLogs[$userId])
                    ? 0
                    : array_sum($accessLogs[$userId]->getPropertyValues('hitcount')),
                'average' => $normMark,
            ];
        }

        return $tally;
    }
}
