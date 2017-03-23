<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Analytics;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
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
use Schoolbox\Assessment\Component\SubmissionBox\WorkType as SubmissionType;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

/**
 * Class CohortAnalysis
 *
 * Object that bundles up all the learning analysis you might want to do against a cohort.
 *
 * A COHORT is defined as: a set of participants involved in any unit imported from any one course specification at a specific point in time.
 *
 *
 * @package Schoolbox\Learning\Analytics
 */
class CohortAnalysis
{

    /**
     * Indexed by unitSpec.id
     *
     * @var Collection|UnitSpecification[]
     */
    private $units;

    /**
     * Folders relevant for retrieving hit counts from
     *
     * @var Collection|Folder[]
     */
    private $classFolders;

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
     * @var Collection $userCache
     */
    private $userCache;

    /**
     * Indexed by assessment.ID.
     * Sorted by sequence, then title
     * Spec ID if it exists, Impl.ID if created in UnitImpl
     *
     * @var Collection|IAssessable[]
     */
    private $assessmentCache;

    /**
     * If non-null, filter results for box-plot of DueWork that does not match this type
     *
     * @var null|SubmissionType
     */
    private $resultFilter;


    /**
     * You need to have FULLY hydrated and linked up your units.
     * This class depends on all unit, assessment, participation, response and feedback references being fully accounted for.
     *
     * @param Collection|IAssessable[] $gradableAssessments IAssessable implementations due in selected Term
     * @param null|SubmissionType      $resultFilter        If supplied, filter results for box-plot of DueWork that does not match this type
     * @throws \DomainException
     */
    public function __construct(Collection $gradableAssessments, SubmissionType $resultFilter = null)
    {
        $this->resultFilter = $resultFilter;
        // TODO - inject mapper dependencies
        $this->units        = UnitSpecificationMapper::getInstance()->createCollection();
        $this->userCache    = UserMapper::getInstance()->createCollection();
        $this->classFolders = FolderMapper::getInstance()->createCollection();
        $this->getClassFolders($gradableAssessments);
        $this->generateUserCache($gradableAssessments);
        $this->aggregateAssessmentImplementations($gradableAssessments);
        $this->calculateProgress();
        $this->calculateNormalisedMarks();
    }


    /**
     * Retrieve all Class Folders
     *
     * @param Collection|IAssessable[] $gradableAssessments
     */
    protected function getClassFolders(Collection $gradableAssessments)
    {
        foreach ($gradableAssessments as $assessment) {
            $folder = $assessment->getBaseContext()->getFolder();
            $this->classFolders->setOffset($folder->getId(), $folder);
        }
    }


    /**
     * @param Collection|IAssessable[] $gradableAssessments
     */
    protected function generateUserCache(Collection $gradableAssessments)
    {
        foreach ($gradableAssessments as $assessment) {
            foreach ($assessment->getAllParticipants() as $participant) {
                $user                            = $participant->getUser();
                $this->userCache[$user->getId()] = $user;
            }
        }
    }


    /**
     * Calculate the normalised marks for:
     * * each student for the course/class
     * * each student per assessment
     */
    protected function calculateNormalisedMarks()
    {
        /**
         * Sets of Assessment + normMark, indexed by user.id
         *
         * @var array $involvedAssessments
         */
        $involvedAssessments = [];

        $assessmentGrades = [];

        foreach ($this->units as $unit) {
            foreach ($unit->getAssessables() as $assessment) {
                $assessmentGrades[$assessment->getId()] = [];

                foreach ($assessment->getAllParticipants() as $participant) {
                    $normMark = $participant->getPublishedNormalisedMark();
                    if (null !== $normMark) {
                        $assessmentGrades[$assessment->getId()][$participant->getUser()->getId()] = $normMark;

                        if ($assessment->getWeight()) {
                            $involvedAssessments[$participant->getUser()->getId()][] = [$assessment, $normMark];
                        }
                    }
                }
            }
        }

        // Ensure assessment grades are in same order as assessment cache
        foreach ($this->assessmentCache as $assessmentId => $assessment) {
            $this->assessmentGrades[$assessmentId] = $assessmentGrades[$assessmentId];
        }


        // Calculate student weighted average mark
        foreach ($involvedAssessments as $userId => $pairs) {

            $weightedSum = 0;
            $totalWeight = 0;

            /**
             * @var array       $pairs
             * @var IAssessable $assessment
             */
            foreach ($pairs as list($assessment, $normMark)) {
                $weight = $assessment->getWeight();

                $weightedSum += $weight * $normMark;
                $totalWeight += $weight;
            }

            $this->courseGrades[$userId] = $weightedSum / $totalWeight;
        }
    }


    /**
     * Calculate student completion progress for course and per unit
     *
     * @throws \DomainException
     */
    protected function calculateProgress()
    {
        /** @var int[] $countCourseAssessmentsInvolved indexed by user.id */
        $countCourseAssessmentsInvolved = [];

        /** @var int[] $countCourseAssessmentsComplete indexed by user.id */
        $countCourseAssessmentsComplete = [];

        foreach ($this->units as $unit) {

            /** @var int[] $countUnitAssessmentsInvolved indexed by user.id */
            $countUnitAssessmentsInvolved = [];

            /** @var int[] $countUnitAssessmentsComplete indexed by user.id */
            $countUnitAssessmentsComplete = [];

            foreach ($unit->getAssessables() as $assessable) {
                foreach ($assessable->getAllParticipants() as $participant) {
                    $countUnitAssessmentsInvolved[$participant->getUser()->getId()]++;
                    $countCourseAssessmentsInvolved[$participant->getUser()->getId()]++;

                    // Has the participant completed this assessment?
                    if (!$participant->isResponseRequired()) {
                        $countUnitAssessmentsComplete[$participant->getUser()->getId()]++;
                        $countCourseAssessmentsComplete[$participant->getUser()->getId()]++;
                    }
                }
            }

            $this->unitProgress[$unit->getId()] = [];

            foreach ($countUnitAssessmentsInvolved as $userId => $countInvolved) {
                $countComplete                               = $countUnitAssessmentsComplete[$userId];
                $this->unitProgress[$unit->getId()][$userId] = 100 * $countComplete / $countInvolved;
            }
        }

        $sumCourseProgress     = 0;
        $highestCourseProgress = 0;

        foreach ($countCourseAssessmentsInvolved as $userId => $countInvolved) {
            $countComplete                 = $countCourseAssessmentsComplete[$userId];
            $courseProgress                = 100 * $countComplete / $countInvolved;
            $this->courseProgress[$userId] = $courseProgress;

            $sumCourseProgress += $courseProgress;
            $highestCourseProgress = max($highestCourseProgress, $courseProgress);
        }

        $this->highestCourseProgress = $highestCourseProgress;

        // Protect from DBZ errors (eg if external is down)
        $denominator = count($this->courseProgress);

        $this->averageCourseProgress = 0 === $denominator ? 0 : $sumCourseProgress / $denominator;
    }


    /**
     * Aggregates all student results per Assessment
     *
     * If AssessmentImpl imported from AssessmentSpec, aggregate by AssessmentSpec
     * If AssessmentImpl created in UnitImpl then index by AssessmentImpl.ID (no aggregation)
     *
     * If a student has feedback for multiple AssessmentImpls imported from a single AssessmentSpec,
     * then their highest mark is retained.
     *
     * @param Collection|IAssessable[] $gradableAssessments Gradable Assessment Implementations
     * @throws \DomainException
     * @todo consider retaining just the most recent feedback for performance - but fragile if we change traversal logic elsewhere
     */
    protected function aggregateAssessmentImplementations(Collection $gradableAssessments)
    {
        $this->assessmentCache = new GenericCollection(IAssessable::class);

        foreach ($gradableAssessments as $assessmentImpl) {

            // Ensure this Assessment is Gradable
            if ($assessmentImpl instanceof Task || 0 === $assessmentImpl->getWeight() || MarkType::SLUG_NONE === $assessmentImpl->getMarkType()->getSlug()) {
                continue;
            }

            $assessmentSpec = $assessmentImpl->getSpecificationAssessment();

            // Was this AssessmentImpl created in UnitImpl ? (not imported from Spec)
            if (!$assessmentSpec) {
                $assessmentSpec = clone $assessmentImpl;

                /** @var UnitImplementation $unitImpl */
                $unitImpl = $assessmentImpl->getBaseContext();
                $assessmentSpec->setContext($unitImpl->getSpecification());
                $assessmentSpec->setId($assessmentImpl->getId());
                $assessmentSpec->setResponses($assessmentSpec->getResponses()->emptyClone());
                $assessmentSpec->setFeedbacks($assessmentSpec->getFeedbacks()->emptyClone());
            }

            // First time we've encountered this assessmentSpec ?
            if (!$this->assessmentCache->offsetExists($assessmentSpec->getId())) {
                // Insert AssessmentSpec into the list.
                $this->assessmentCache->setOffset($assessmentSpec->getId(), $assessmentSpec);
            } else {
                // De-duplicate AssessmentSpec
                $assessmentSpec = $this->assessmentCache->getOffset($assessmentSpec->getId());
            }

            foreach ($assessmentImpl->getAllParticipants() as $implParticipant) {

                // Is this student not already a Participant of the Spec ?
                $specParticipant = $assessmentSpec->getAllParticipants()->getOffset($implParticipant->getUser()->getId());
                if (!$specParticipant) {
                    // Insert student into Spec participation
                    $specParticipant = new Participant($implParticipant->getUser(), $assessmentSpec);
                    $assessmentSpec->getAllParticipants()->setOffset($specParticipant->getUser()->getId(), $specParticipant);
                }

                // Has the implParticipant been given a mark ?
                $implMark = $implParticipant->getPublishedNormalisedMark();
                if (null !== $implMark) {

                    // Does the implParticipant have a higher mark than the current specParticipant?
                    $specMark = $specParticipant->getPublishedNormalisedMark();
                    if (null === $specMark || $implMark > $specMark) {

                        // Replace the SpecParticipant's interactions with this ImplParticipant's interactions

                        // Remove existing SpecParticipant Feedback
                        foreach ($specParticipant->getAllFeedbacks() as $feedbackId => $feedback) {
                            $assessmentSpec->getFeedbacks()->offsetUnset($feedbackId);
                        }
                        $specParticipant->setAllFeedbacks($specParticipant->getAllFeedbacks()->emptyClone());

                        // Remove existing SpecParticipant Feedback
                        foreach ($specParticipant->getAllResponses() as $responseId => $response) {
                            $assessmentSpec->getResponses()->offsetUnset($responseId);
                        }
                        $specParticipant->setAllResponses($specParticipant->getAllResponses()->emptyClone());


                        // Copy IFeedback
                        foreach ($implParticipant->getAllFeedbacks() as $feedback) {
                            $specFeedback = clone $feedback;
                            $specFeedback->setId($feedback->getId());
                            $specFeedback->setAssessable($assessmentSpec);
                            $specParticipant->getAllFeedbacks()->setOffset($specFeedback->getId(), $specFeedback);
                            $assessmentSpec->getFeedbacks()->setOffset($specFeedback->getId(), $specFeedback);
                        }

                        // Copy IResponse
                        foreach ($implParticipant->getAllResponses() as $response) {
                            $specResponse = clone $response;
                            $specResponse->setId($specResponse->getId());
                            $specResponse->setAssessable($assessmentSpec);
                            $specParticipant->getAllResponses()->setOffset($specResponse->getId(), $specResponse);
                            $assessmentSpec->getResponses()->setOffset($specResponse->getId(), $specResponse);
                        }
                    }
                }
            }
        }

        // Aggregate by, and insert into, UnitSpec
        foreach ($this->assessmentCache as $assessment) {
            $unitSpec = $assessment->getContext();
            $unitSpec->getAssessables()->setOffset($assessment->getId(), $assessment);
            $this->units->setOffset($unitSpec->getId(), $unitSpec);
        }

        // Sort AssessmentCache by sequence, then title
        $this->assessmentCache
            ->sortByCallback(function (IAssessable $left, IAssessable $right) {
                if ($left->getSequence() < $right->getSequence()) {
                    return -1;
                } elseif ($left->getSequence() > $right->getSequence()) {
                    return +1;
                } else {
                    return strcasecmp($left->getTitle(), $right->getTitle());
                }
            })
            ->indexBy('id');
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

        foreach ($this->unitProgress as $unitSpecId => $unitProgress) {
            $maxProgress   = 0;
            $sumProgress   = 0;
            $countStudents = 0;
            foreach ($unitProgress as $userId => $studentProgress) {
                $countStudents++;
                $sumProgress += $studentProgress;
                $maxProgress = max($maxProgress, $studentProgress);
            }
            $progress[$unitSpecId] = [
                'unit'                => $this->units[$unitSpecId],
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
     * @return Collection|IAssessable[]
     */
    public function getDeduplicateAssessments()
    {
        $filteredAssessments = $this->assessmentCache->emptyClone();

        foreach ($this->assessmentCache as $assessment) {
            if (!$this->isExcludedBySubmissionTypeFilter($assessment)) {
                $filteredAssessments[$assessment->getId()] = $assessment;
            }
        }
        return $filteredAssessments;
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
        // Exclude if we are filtering results, and this assessment is a dueWork that does not match the filter.
        // Quizzes are never excluded (for now - at some point SubmissionType should be promoted to IAssessable)
        return $this->resultFilter && $assessment instanceof DueWork && $assessment->getWorkType()->getId() !== $this->resultFilter->getId();
    }


    /**
     * Gets the student marks to display in the 'Results' box-plot.
     * Respects submission-type filtering
     *
     * @return mixed[][]
     * @var mixed[] $results [$assessmentId]
     * @var User    $results [$assessmentId][$userId]['user']
     * @var float   $results [$assessmentId][$userId]['grade']
     */
    public function getAssessmentMarks()
    {
        $results = [];

        foreach ($this->assessmentGrades as $assessmentId => $studentMarks) {
            if (!$this->isExcludedBySubmissionTypeFilter($this->assessmentCache[$assessmentId])) {
                $results[$assessmentId] = [];
                foreach ($studentMarks as $userId => $normMark) {
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
     * Returns five-point grade data from unit implementations for all participants, indexed by rubric indicator
     * Applicable to the rubric box-and-whisker graph
     *
     * (Cuts the same data as getCohortGradesByAssessment() but by indicators instead of assessment.)
     *
     * @param GenericCollection|UnitImplementation[] $unitImplementations
     * @param Collection|User[][]                    $unitImplStudents Grouped by unitImpl.id,
     * @return array
     */
    public function getCohortGradesByIndicator(GenericCollection $unitImplementations, Collection $unitImplStudents)
    {
        // exploit old class
        return (new AllRubrics($unitImplementations, $unitImplStudents))->getRubricData();
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
        $folderIds = $this->classFolders->getColumnValues('id');
        $userIds   = array_keys($this->courseGrades);

        /** @var Collection|Collection[]|FolderLog[][] $accessLogs */
        $accessLogs = FolderLogMapper::getInstance()->findByColumnList([
            'user' => $userIds,
            'cid'  => $folderIds,
        ])->groupByCallback(function (FolderLog $log) {
            return $log->getUser()->getId();
        });

        foreach ($this->courseGrades as $userId => $normMark) {
            $tally[$userId] = [
                'user'    => $this->userCache[$userId],
                'hits'    => empty($accessLogs[$userId])
                    ? 0
                    : array_sum($accessLogs[$userId]->getColumnValues('hitcount')),
                'average' => $normMark,
            ];
        }

        return $tally;
    }
}
