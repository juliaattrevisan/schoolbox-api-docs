<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\SortUsersByName;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use InvalidArgumentException;

/**
 * Composed of student-assessment participation data.
 * Provides convenient access to render the course markbook and derive aggregate statistics.
 *
 * A course markbook is a table composed of columns and rows.
 * The columns are assessments that have been collapsed by assessment specification, then grouped by unit-spec.
 * The rows are course-imports. Course-imports are usually 1:1 with classes, but will be N:1 if the same course
 * is imported multiple times to a class.
 *
 * Structure/Rules:
 * * A course-markbook contains 0:N course-imports.
 * * Assessments only reside inside course-units, never directly on folder
 * * Assessment statistics are aggregated over assessment participants
 * * Student statistics are aggregated over assessments in a course-import
 * * A course-import has 1:N unit-imports
 * * Each unit contains a column for each imported spec, plus a column per assessment-impl that was not imported
 *   eg. UnitX had 2 assessment-specs, was imported twice, then each of those imports had a new assessment-impl created
 *       UnitX would have 2 columns for the specs, and another 2 for the impls. The spec columns would span both
 *       course-imports, the impl-only columns would each span only once course-import
 *
 * All visibility, aggregation, data structure, sorting etc logic should occur here, not it twig.
 */
class CourseMarkbook
{
    /**
     * Represents column headers. May be assessment specifications or implementations.
     *
     * Sorted by course-markbook ordering. Indexed by assessment.id
     *
     * Controls the horizontal ordering. ie iterate this for each row
     *
     * @var GenericCollection|IAssessable[]
     */
    private $collapsedAssessments;


    /**
     * Average (non-weighted) student mark for each assessment implementation. Includes marks from draft feedbacks.
     *
     * Indexed by [assessmentImpl.id]['any'|'published']
     *
     * Won't have an 'any' mark if there are no pending drafts for students in an assessment
     *
     * @var float[]
     */
    private $assessmentImplAverages;


    /**
     * Average (non-weighted) student mark across all assessment impls imported from a spec. Includes marks from draft feedbacks.
     *
     * Indexed by [assessmentSpec.id]['any'|'published']
     *
     * Won't have an 'any' mark if there are no pending drafts for students in an assessment
     *
     * @var float[][]
     */
    private $assessmentSpecAverages;


    /**
     * Weighted average student mark for each course import. Includes drafts.
     *
     * Indexed by [courseImport.id][student.id]['any'|'published']['norm'|'hr']
     *
     * Won't have an 'any' mark if this no there are no pending drafts for the student
     *
     * @var float[][][][]|string[][][][]
     */
    private $studentAverageMarks;

    /**
     * All encountered unit specs.
     *
     * Either because an assessment was imported from the unit-spec, or because an assessment
     * has been created in a unit-impl that was imported from the unit-spec.
     *
     * Indexed by unitSpec.id
     *
     * @var UnitSpecification\Model[]|GenericCollection
     */
    private $unitSpecs;

    /**
     * All course-imports where assessments reside.
     *
     * Indexed by courseImpl.id
     * Sorted by course folder name, then course import first unit open date, then course import id
     *
     * This last of the sort is to cater for a course being imported multiple times to the same folder, and matches the logic
     * for assessments in the SortForCourseMarkbook class.
     *
     * Defines the row ordering.
     *
     * @var CourseImplementation\Model[]|GenericCollection
     */
    private $courseImports;

    /**
     * Assessment implementations grouped by course implementation
     *
     * Indexed by courseImpl.id, then either assessmentImpl.spec.id if imported from spec, or assessmentImpl.id if created in class's unit
     *
     * @var IAssessable[][]|GenericCollection[]|GenericCollection
     */
    private $courseImportAssessments;

    /**
     * Grouped by course import.
     *
     * Sorted by last name, first name. Defines row ordering inside each course-import.
     *
     * @var User[][]|GenericCollection[]|GenericCollection
     */
    private $courseImportStudents;

    /**
     * Indexed by [courseImplId][assessmentId][userId]['published'|'draft']['norm'|'hr']
     *
     * assessmentId will be for spec if imported from spec, for impl otherwise
     *
     * No 'draft' mark included if there is no pending draft.
     *
     * Note that it's quite possible for a pending draft to have the same value as the published mark.
     *
     * @var float[][][][][]
     */
    private $studentMarks;


    /**
     * Indexed by [courseImplId][assessmentId][userId]
     *
     * @var string[][][]
     */
    private $studentComments;

    /**
     * Indexed by [courseImplId][assessmentId][userId]
     *
     * assessmentId will be for spec if imported from spec, for impl otherwise
     *
     * @var int[][][]
     */
    private $taskProgress;

    /** @var IConfigLoader */
    private $config;

    /**
     * CourseMarkbook constructor.
     *
     * @param CourseSpecification\Model       $courseSpec
     * @param GenericCollection|IAssessable[] $assessables                    Must be indexed by id. (Ensures we don't have duplicates)
     * @param User                            $viewingUser
     * @param IConfigLoader                   $config
     * @throws InvalidArgumentException If a caller attempts to build a course-markbook from invalid components.
     * @throws \UnexpectedValueException If we attempt to add an invalid type to a generic collection.
     */
    public function __construct(
        CourseSpecification\Model $courseSpec,
        GenericCollection $assessables,
        User $viewingUser,
        IConfigLoader $config
    ) {
        $this->config = $config;
        // Ensure the data passed in is all valid and makes sense
        $this->validateCourseMarkbookAssessables($courseSpec, $assessables);

        // Build this into a form suitable for rendering efficiently
        $this->buildDataStructures($assessables);
        $this->sortDataStructures();
        $this->collateStatistics($assessables, $viewingUser);
    }


    /**
     * @return GenericCollection|IAssessable[]
     */
    public function getCollapsedAssessments(): GenericCollection
    {
        return $this->collapsedAssessments;
    }


    /**
     * @return IAssessable[][]|GenericCollection[]|GenericCollection
     */
    public function getUnitSpecs(): GenericCollection
    {
        return $this->unitSpecs;
    }


    /**
     * @return CourseImplementation\Model[]|GenericCollection
     */
    public function getCourseImpls(): GenericCollection
    {
        return $this->courseImports;
    }


    /**
     * @return IAssessable[][]|GenericCollection[]|GenericCollection
     */
    public function getCourseImportAssessments(): GenericCollection
    {
        return $this->courseImportAssessments;
    }


    /**
     * @return User[][]|GenericCollection[]|GenericCollection
     */
    public function getCourseImportStudents(): GenericCollection
    {
        return $this->courseImportStudents;
    }


    /**
     * @return float[][]
     */
    public function getAssessmentSpecAverages(): array
    {
        return $this->assessmentSpecAverages;
    }


    /**
     * @return float[][]
     */
    public function getAssessmentImplAverages(): array
    {
        return $this->assessmentImplAverages;
    }


    /**
     * @return float[][][][][]
     */
    public function getStudentMarks(): array
    {
        return $this->studentMarks;
    }


    /**
     * @return float[][][][]|string[][][][]
     */
    public function getStudentAverageMarks(): array
    {
        return $this->studentAverageMarks;
    }

    /**
     * @return string[][][]
     */
    public function getStudentComments(): array
    {
        return $this->studentComments;
    }

    /**
     * @return int[][][]
     */
    public function getTaskProgress(): array
    {
        return $this->taskProgress;
    }


    /**
     * Ensure only valid assessments are used to build the course markbook
     *
     * @param CourseSpecification\Model       $courseSpec
     * @param GenericCollection|IAssessable[] $assessables
     * @throws InvalidArgumentException If any assessment is NQR for this course markbook.
     */
    private function validateCourseMarkbookAssessables(
        CourseSpecification\Model $courseSpec,
        GenericCollection $assessables
    ): void {
        foreach ($assessables as $assessmentId => $assessable) {
            /** @var UnitImplementation\Model $baseContext */
            $baseContext = $assessable->getBaseContext();
            if (!$baseContext instanceof UnitImplementation\Model) {
                throw new InvalidArgumentException('Course markbook may only contain assessments that reside in a unit-implementation');
            }
            if ($assessable->isSpecification()) {
                throw new InvalidArgumentException('Course markbook may only contain assessment implementations');
            }
            if ($baseContext->getCourse()->getSpecification()->getId() !== $courseSpec->getId()) {
                throw new InvalidArgumentException('Assessment does not belong to course.');
            }
        }
    }


    /**
     * Iterate all assessments to derive the list of:
     * * UnitSpecs
     * * CourseImpls
     *
     * @param GenericCollection|IAssessable[] $assessmentImpls
     * @throws \UnexpectedValueException If we attempt to add an invalid element to a generic collection.
     */
    private function buildDataStructures(GenericCollection $assessmentImpls): void
    {
        $this->collapsedAssessments    = new GenericCollection(IAssessable::class);
        $this->unitSpecs               = new GenericCollection(UnitSpecification\Model::class);
        $this->courseImports           = new GenericCollection(CourseImplementation\Model::class);
        $this->courseImportAssessments = new GenericCollection(GenericCollection::class);
        $this->courseImportStudents    = new GenericCollection(GenericCollection::class);

        // Find all unitSpecs and course-impls which assessments belong too.
        foreach ($assessmentImpls as $aImpl) {
            /** @var UnitImplementation\Model $unitImpl */
            $unitImpl                           = $aImpl->getBaseContext();
            $unitSpec                           = $unitImpl->getSpecification();
            $unitSpecId                         = $unitSpec->getId();
            $this->unitSpecs[$unitSpecId]       = $unitSpec;
            $courseImpl                         = $unitImpl->getCourse();
            $courseImplId                       = $courseImpl->getId();
            $this->courseImports[$courseImplId] = $courseImpl;
            $aSpec                              = $aImpl->getSpecificationAssessment();

            if (!$this->courseImportAssessments->keyExists($courseImplId)) {
                $this->courseImportAssessments[$courseImplId] = new GenericCollection(IAssessable::class);
            }
            if ($aSpec) {
                // Was this assessment imported from the course spec? And hence can be collapsed?
                $this->collapsedAssessments[$aSpec->getId()]                   = $aSpec;
                $this->courseImportAssessments[$courseImplId][$aSpec->getId()] = $aImpl;
            } else {
                // Or was it created in a unit-impl after import.
                $this->collapsedAssessments[$aImpl->getId()]                   = $aImpl;
                $this->courseImportAssessments[$courseImplId][$aImpl->getId()] = $aImpl;
            }

            if (!$this->courseImportStudents->keyExists($courseImplId)) {
                $this->courseImportStudents[$courseImplId] = new GenericCollection(User::class);
            }
            $courseStudents = $this->courseImportStudents[$courseImplId];
            foreach ($aImpl->getAllParticipants() as $participant) {
                $user                           = $participant->getUser();
                $courseStudents[$user->getId()] = $user;
            }
        }
    }


    /**
     * Sorts the data structures suitable for rendering
     *
     * Sorts the assessments horizontally,
     * Sorts the course imports and participant lists vertically
     */
    private function sortDataStructures(): void
    {
        // Column ordering
        $this->collapsedAssessments->sortByCallback((new SortForCourseMarkbook())->getCallable(), true);

        // Row ordering (first sort attribute)
        $this->courseImports->sortByCallback(function (CourseImplementation\Model $left, CourseImplementation\Model $right) {
            // Course Import's folder name
            $folderNameCompare = strnatcasecmp($left->getFolder()->getTitle(), $right->getFolder()->getTitle());
            if (0 !== $folderNameCompare) {
                return $folderNameCompare;
            }

            // Course-imports first unit open date
            $firstLeftUnitImpl   = $this->getFirstUnitImpl($left);
            $firstRightUnitImpl  = $this->getFirstUnitImpl($right);
            $unitOpenDateCompare = $firstLeftUnitImpl->getOpenDate()->compare($firstRightUnitImpl->getOpenDate());
            if (0 !== $unitOpenDateCompare) {
                return $unitOpenDateCompare;
            }

            // Course import id
            return $left->getId() <=> $right->getId();
        }, true);

        // Row ordering (second sort attribute)
        $sortByNameComparator = new SortUsersByName();
        foreach ($this->courseImportStudents as $courseImportId => $students) {
            $students->sortByCallback($sortByNameComparator->getCallable(), true);
        }
    }


    /**
     * Returns the earliest opening unit implementation found in a course-implementation
     *
     * @param CourseImplementation\Model $courseImpl
     * @return UnitImplementation\Model
     */
    private function getFirstUnitImpl(CourseImplementation\Model $courseImpl): UnitImplementation\Model
    {
        /** @var UnitImplementation\Model $firstUnitImpl */
        $firstUnitImpl = null;
        foreach ($courseImpl->getUnits() as $unitImpl) {
            if (!$firstUnitImpl || $unitImpl->getOpenDate()->lt($firstUnitImpl->getOpenDate())) {
                $firstUnitImpl = $unitImpl;
            }
        }
        return $firstUnitImpl;
    }


    /**
     * Calculates all assessment and student (non-weighted) averages
     *
     * @param GenericCollection|IAssessable[] $assessmentImpls
     * @param User                            $viewingUser
     */
    private function collateStatistics(GenericCollection $assessmentImpls, User $viewingUser): void
    {
        // Individual participant results, respecting assessment's feedback visibility.
        $this->studentMarks    = [];
        $this->studentComments = [];
        $this->taskProgress    = [];

        $courseImplMarkScheme = new Collection();

        // Student weighted average across all assessment in a course.
        $this->studentAverageMarks = [];

        /** @var float[][][][] $studentAverageMarks */
        $studentAverageMarks = [];               // Indexed by [courseImpl.id][user.id]['any'|'published']['sum'|'weight'].

        /** @var bool[] $studentMarkContainsDrafts Track whether a student's weighted average contains a draft mark */
        $studentMarkContainsDrafts = [];         // indexed by user.id

        // Average (non-weighted) marks per assessment.
        $this->assessmentImplAverages = [];
        $this->assessmentSpecAverages = [];

        /** @var float[][] $specMarks */
        $specMarks = [];                         // Indexed by [aSpec.id]['any'|'published']

        // bool[]. Track whether any aggregate assessment marks are composed of draft marks
        $assessmentImplMarkContainsDrafts = [];  // indexed by aImpl.id
        $assessmentSpecMarkContainsDrafts = [];  // indexed by aSpec.id

        $markSchemaFactory = new Factory($this->config);

        foreach ($assessmentImpls as $aImpl) {
            if (!$aImpl->isFeedbackVisibleToUser($viewingUser)) {
                continue;
            }
            $aImplId                       = $aImpl->getId();
            $aSpec                         = $aImpl->getSpecificationAssessment();
            $aId                           = $aSpec ? $aSpec->getId() : $aImplId;
            $assessmentAnyMarks            = [];                                   // Participants' marks for this assessment impl, including drafts.
            $assessmentPublishedMarks      = [];                                   // Participants' marks for this assessment impl, published only.
            $weight                        = $aImpl->getWeight();
            $isTopLevelAssessable          = $aImpl->isBaseAssessable();           // Don't aggregate student average for project sub-assessments

            /** @var UnitImplementation\Model $unitImpl */
            $unitImpl     = $aImpl->getBaseContext();
            $courseImplId = $unitImpl->getCourse()->getId();
            $courseImplFolder = $unitImpl->getCourse()->getFolder();

            // find mark scheme for the course impl, if not yet
            if (!$courseImplMarkScheme->keyExists($courseImplFolder->getId())) {
                $implMarkScheme = $markSchemaFactory->spawnForFolder($courseImplFolder, $viewingUser->getRole()->isStaff());
                $courseImplMarkScheme->setOffset($courseImplId, $implMarkScheme);
            }

            foreach ($aImpl->getAllParticipants() as $participant) {
                if ($participant->isExcluded()) {
                    continue;
                }
                $userId = $participant->getUser()->getId();

                if ($aImpl instanceof Task\Model) {
                    // Simple for tasks, just record the progress state. No need to distinguish drafts.
                    if ($participant->isResponseRequired()) {
                        $status = Completion::STATUS_INCOMPLETE;
                    } elseif ($participant->isFeedbackRequired()) {
                        $status = Completion::STATUS_SELF_ASSESSED_COMPLETE;
                    } else {
                        $status = Completion::STATUS_TEACHER_ASSESSED_COMPLETE;
                    }
                    $this->taskProgress[$courseImplId][$aId][$userId] = $status;
                    continue;
                }

                if ($aImpl instanceof Project) {
                    // Special case for project. No directly attached feedbacks.
                    $authNormMark      = $participant->getNormalisedMark(false);
                    $publishedNormMark = $participant->getNormalisedMark(true);
                    if (null !== $authNormMark) {
                        $authHrMark           = $participant->getHumanReadableMark(false);
                        $assessmentAnyMarks[] = $authNormMark;

                        if ($isTopLevelAssessable) {
                            // First time we've encountered this row (user + course-import) ?
                            $studentAverageMarks[$courseImplId][$userId]['any'] ??= ['sum' => 0, 'weight' => 0];
                            $studentAverageMarks[$courseImplId][$userId]['any']['sum']    += $weight * $authNormMark;
                            $studentAverageMarks[$courseImplId][$userId]['any']['weight'] += $weight;
                        }

                        // Only store 'any' mark if is different from 'published' mark, indicating it is composed of at least 1 draft.
                        if ($authNormMark !== $publishedNormMark) {
                            // We can short-circuit the conclusion here if the published-only mark differs from the 'any' mark.
                            $pendingDraft = true;
                        } else {
                            // Not sure. Need to go the long way.
                            $pendingDraft = $aImpl->hasPendingDraftAuthoritativeFeedback($participant);
                        }
                        if ($pendingDraft) {
                            $this->studentMarks[$courseImplId][$aId][$userId]['draft']['norm'] = $authNormMark;
                            $this->studentMarks[$courseImplId][$aId][$userId]['draft']['hr']   = $authHrMark;

                            $assessmentImplMarkContainsDrafts[$aImplId] = true;
                        }
                        if ($aSpec) {
                            $specMarks[$aSpec->getId()]['any'][] = $authNormMark;
                            if ($pendingDraft) {
                                $assessmentSpecMarkContainsDrafts[$aSpec->getId()] = true;
                            }
                        }
                    }
                    if (null !== $publishedNormMark) {
                        $publishedHrMark            = $participant->getHumanReadableMark(true);
                        $assessmentPublishedMarks[] = $publishedNormMark;

                        if ($isTopLevelAssessable) {
                            // First time we've encountered this row (user + course-import) ?
                            $studentAverageMarks[$courseImplId][$userId]['published'] ??= ['sum' => 0, 'weight' => 0];
                            $studentAverageMarks[$courseImplId][$userId]['published']['sum']    += $weight * $publishedNormMark;
                            $studentAverageMarks[$courseImplId][$userId]['published']['weight'] += $weight;
                        }

                        // Always store published if it exists
                        $this->studentMarks[$courseImplId][$aId][$userId]['published']['norm'] = $publishedNormMark;
                        $this->studentMarks[$courseImplId][$aId][$userId]['published']['hr']   = $publishedHrMark;
                        if ($aSpec) {
                            $specMarks[$aSpec->getId()]['published'][] = $publishedNormMark;
                        }
                    }
                    continue;
                }

                // Quiz/DueWork.
                // We need to distinguish when a draft mark is pending, even if the mark is the same.
                $authAnyFeedback       = $participant->getAuthoritativeFeedback(false);
                $authPublishedFeedback = $participant->getAuthoritativeFeedback(true);

                if ($aImpl instanceof DueWork\Model && $authPublishedFeedback) {
                    // Store due-work feedback published comments
                    $this->studentComments[$courseImplId][$userId][$aId] = $authPublishedFeedback->getComment();
                }

                $authNormMark      = $authAnyFeedback ? $authAnyFeedback->getNormalisedMark() : null;
                $publishedNormMark = $authPublishedFeedback ? $authPublishedFeedback->getNormalisedMark() : null;

                // Always store the published mark if one exists for a student (even if null)
                if ($authPublishedFeedback) {
                    // Record that an assessment participant has a published mark
                    if (null !== $publishedNormMark) {
                        $assessmentPublishedMarks[] = $publishedNormMark;
                    }

                    // Record the student's published mark so we can display in a table cell
                    $this->studentMarks[$courseImplId][$aId][$userId]['published']['norm'] = $publishedNormMark;
                    $this->studentMarks[$courseImplId][$aId][$userId]['published']['hr']   = $authPublishedFeedback->getHumanReadableMark();
                }

                // Only store a draft mark if a draft mark is pending for a student (even if it is null)
                $pendingDraft = $authAnyFeedback && $authAnyFeedback->isDraft();
                if ($pendingDraft) {
                    $this->studentMarks[$courseImplId][$aId][$userId]['draft']['norm'] = $authNormMark;
                    $this->studentMarks[$courseImplId][$aId][$userId]['draft']['hr']   = $authAnyFeedback->getHumanReadableMark();
                }


                // Either draft or published mark
                if (null !== $authNormMark) {
                    // Record that an assessment participant has a mark
                    $assessmentAnyMarks[] = $authNormMark;

                    if ($isTopLevelAssessable) {
                        // First time we've encountered this row (user + course-import) ?
                        $studentAverageMarks[$courseImplId][$userId]['any'] ??= ['sum' => 0, 'weight' => 0];
                        $studentAverageMarks[$courseImplId][$userId]['any']['sum']    += $weight * $authNormMark;
                        $studentAverageMarks[$courseImplId][$userId]['any']['weight'] += $weight;
                    }

                    if ($authAnyFeedback->isDraft()) {
                        $assessmentImplMarkContainsDrafts[$aImplId] = true;
                        $studentMarkContainsDrafts[$userId]         = true;
                    }
                    if ($aSpec) {
                        $specMarks[$aSpec->getId()]['any'][] = $authNormMark;
                        if ($pendingDraft) {
                            $assessmentSpecMarkContainsDrafts[$aSpec->getId()] = true;
                        }
                    }
                }

                // Just published marks
                if (null !== $publishedNormMark) {
                    if ($isTopLevelAssessable) {
                        // First time we've encountered this row (user + course-import) ?
                        $studentAverageMarks[$courseImplId][$userId]['published'] ??= ['sum' => 0, 'weight' => 0];
                        $studentAverageMarks[$courseImplId][$userId]['published']['sum']    += $weight * $publishedNormMark;
                        $studentAverageMarks[$courseImplId][$userId]['published']['weight'] += $weight;
                    }

                    if ($aSpec) {
                        $specMarks[$aSpec->getId()]['published'][] = $publishedNormMark;
                    }
                }
            }

            // Calculate average mark for this assessmentImpl
            if (\count($assessmentAnyMarks)) {
                $this->assessmentImplAverages[$aImplId]['any'] = \array_sum($assessmentAnyMarks) / \count($assessmentAnyMarks);
            }


            // Calculate average published mark for this assessmentImpl
            if (\count($assessmentPublishedMarks)) {
                $this->assessmentImplAverages[$aImplId]['published'] =  \array_sum($assessmentPublishedMarks) / \count($assessmentPublishedMarks);
            }
        }

        // Unset any aggregate assessment impl marks that were not built from published only but didn't encounter any drafts
        foreach ($this->assessmentImplAverages as $aImplId => $marks) {
            if (!\array_key_exists($aImplId, $assessmentImplMarkContainsDrafts)) {
                unset($this->assessmentImplAverages[$aImplId]['any']);
            }
        }

        // Resolve assessmentSpec averages
        foreach ($specMarks as $aSpecId => $marksForType) {
            foreach ($marksForType as $markType => $marks) {
                /** @var float[] $marks */
                $this->assessmentSpecAverages[$aSpecId][$markType] = \array_sum($marks) / \count($marks);
            }
        }
        // Unset any aggregate assessment spec marks that were not built from published only but didn't encounter any drafts
        foreach ($this->assessmentSpecAverages as $aSpecId => $marksForType) {
            if (!\array_key_exists($aSpecId, $assessmentSpecMarkContainsDrafts)) {
                unset($this->assessmentSpecAverages[$aSpecId]['any']);
            }
        }

        // Resolve student weighted averages
        foreach ($studentAverageMarks as $courseImplId => $courseMarks) {
            foreach ($courseMarks as $userId => $studentCourseMarks) {
                foreach ($studentCourseMarks as $type => $sumAndWeight) {
                    $studentAverageMarks[$courseImplId][$userId][$type] = $sumAndWeight['weight']
                        ? $sumAndWeight['sum'] / $sumAndWeight['weight']
                        : null;
                }
            }
        }

        // Unset any student aggregate mark if it was not composed of any drafts
        foreach ($studentAverageMarks as $courseImplId => $courseMarks) {
            foreach ($courseMarks as $userId => $studentCourseMarks) {
                if (!\array_key_exists($userId, $studentMarkContainsDrafts)) {
                    unset($studentAverageMarks[$courseImplId][$userId]['any']);
                }
            }
        }

        // And convert into human-readable form
        foreach ($studentAverageMarks as $courseImplId => $courseMarks) {
            foreach ($courseMarks as $userId => $studentCourseMarks) {
                foreach ($studentCourseMarks as $publishType => $normalised) {
                    if (!$courseImplMarkScheme->getOffset($courseImplId)) {
                        trigger_error("Mark does not exist for Course Impl:$courseImplId and User: $userId", E_USER_WARNING);
                        continue;
                    }
                    /** @var float $normalised */
                    $this->studentAverageMarks[$courseImplId][$userId][$publishType] = [
                        'norm' => $normalised,
                        'hr'   => $courseImplMarkScheme->getOffset($courseImplId)->getHumanReadableMark($normalised),
                    ];
                }
            }
        }
    }

    /**
     * Build student ranking based on whole cohort (course) not just single class.
     * See also similar function in library/Schoolbox/Learning/Markbook/Markbook.php.
     * @return array (integer ranking indexed by studentId)
     */
    public function getRanking(): array
    {
        // First build basic array (id,grade).
        $rankList = [];
        foreach ($this->studentAverageMarks as $course) {
            foreach ($course as $studentId => $studentStatistic) {
                if (isset($studentStatistic['published']['norm'])) {
                    $rankList[] = [
                        'id'    => $studentId,
                        'grade' => $studentStatistic['published']['norm'],
                    ];
                }
            }
        }

        // Now sort based on grade.
        usort($rankList, function ($one, $two) {
            return $two['grade'] <=> $one['grade'];
        });

        $rankValue            = 1;
        $currentRank          = 1;
        $prevStudent['grade'] = '';

        /**
         * iterate over ordered list of ranked students, and actually give them helpful rankings
         * not 42.06, but 5 (you are 5th in the class)
         */
        $rankByStudentId = [];
        foreach ($rankList as $student) {
            if ($prevStudent['grade'] !== $student['grade']) {
                $rankValue = $currentRank;
            }
            $rankByStudentId[$student['id']] = $rankValue;
            $currentRank++;
            $prevStudent = $student;
        }

        return $rankByStudentId;
    }
}
