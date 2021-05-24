<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;

class MiddleYearsAssessmentRank extends BaseComponent
{

    /**
     * 'MiddleYearsAssessmentRank' assessments across the cohort
     *
     * @var GenericCollection|IAssessable[]
     */
    private $cohortAssessments;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var Folder\Model */
    private $folder;


    /**
     * This is the human-readable version of the workTypeId that was used to calculate
     * $cohortAssessments. This class serves an error message containing the worktype's
     * name that can be exposed to the user for debugging purposes
     *
     * @var string
     */
    private string $workTypeName;

    /**
     * Constructor
     *
     * @param GenericCollection|IAssessable[] $cohortAssessments Must only contain 'MiddleYearsAssessmentRank' assessments
     * @param User                            $student
     * @param StudentAttributes               $attributes
     * @param Visibility                      $visibility
     * @param User                            $viewer
     * @param Folder\Model                    $folder
     * @param string                          $workTypeName
     * @param string|null                     $roleTypeOverride
     * @throws InvalidArgumentException
     */
    public function __construct(
        GenericCollection $cohortAssessments,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        Folder\Model $folder,
        string $workTypeName,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);

        $this->cohortAssessments = $cohortAssessments;
        $this->student           = $student;
        $this->visibility        = $visibility;
        $this->folder            = $folder;
        $this->workTypeName      = $workTypeName;
    }


    /**
     * Returns an array of data, or NULL if component should not be shown at all
     *
     * @return null|array
     * @throws UnexpectedValueException
     */
    public function toContext(): ?array
    {
        // Group cohort assessments per class
        /** @var Collection|GenericCollection[]|IAssessable[][] $cohortAssessmentsPerClass */
        $cohortAssessmentsPerClass = $this->cohortAssessments->groupByCallback(function (IAssessable $assessable) {
            return $assessable->getBaseContext()->getFolder()->getId();
        });

        foreach ($cohortAssessmentsPerClass as $folderId => $classAssessments) {
            // Check all instances of the configured work type are of correct IAssessable type, and Mark Type
            foreach ($classAssessments as $classAssessment) {
                if (!$classAssessment instanceof DueWork\Model) {
                    throw new UnexpectedValueException("A '{$this->workTypeName}' assessment ({$classAssessment->getTitle()}) must be a Due Work. '{$classAssessment->getTitle()}' is a '{$classAssessment::getDefaultTypeName()}'");
                }
            }
        }

        // Check that students only participate in max 1 HscAssessmentRank assessment per Folder per ReportPeriod
        // (Note we are already in the context of a single ReportPeriod)
        /** @var int[][] $participationCountsPerFolder Indexed by folderId, studentId */
        $participationCountsPerFolder = [];

        foreach ($cohortAssessmentsPerClass as $folderId => $classAssessments) {
            $participationCountsPerFolder[$folderId] = [];
            foreach ($classAssessments as $classAssessment) {
                foreach ($classAssessment->getAllParticipants() as $participant) {
                    if ($participant->isExcluded()) {
                        continue;
                    }
                    $studentId = $participant->getUser()->getId();
                    if (!array_key_exists($studentId, $participationCountsPerFolder[$folderId])) {
                        $participationCountsPerFolder[$folderId][$studentId] = 0;
                    }
                    $participationCountsPerFolder[$folderId][$studentId]++;
                }
            }
        }

        $errors = [];

        foreach ($participationCountsPerFolder as $folderId => $participationCounts) {
            /** @var IAssessable $firstAssessment */
            $firstAssessment = $cohortAssessmentsPerClass[$folderId]->first();
            $folder          = $firstAssessment->getBaseContext()->getFolder();
            $folderLink      = sprintf('<a href="/homepage/%d">%s</a>', $folder->getId(), $folder->getTitle());
            foreach ($participationCounts as $studentId => $count) {
                if ($count >= 2) {
                    $errors[] = "$folderLink: Student: $studentId, Count: $count";
                }
            }
        }

        if (count($errors)) {
            $msg = "Invalid data. Students may only participate in 1 <strong>{$this->workTypeName}</strong> assessment per class per report period.<br>\n" .
                implode("<br>\n", $errors);
            throw new UnexpectedValueException($msg);
        }

        // Check that this class has an instance of the expected work type
        if (!$cohortAssessmentsPerClass->keyExists($this->folder->getId()) || !$cohortAssessmentsPerClass[$this->folder->getId()]->count()) {
            throw new UnexpectedValueException("No '{$this->workTypeName}' assessment found for class and report period");
        }

        // Check whether the component should be visible
        if (!$this->deriveComponentVisibility($cohortAssessmentsPerClass[$this->folder->getId()])) {
            return null;
        }

        /* If the student doesn't have a feedback here, return null; they don't have a Summative Grade
         * but they might have a Formative Grade which is derived separately
         */
        $feedback = $this->findStudentFeedback($cohortAssessmentsPerClass[$this->folder->getId()]);

        if (null === $feedback) {
            return null;
        }

        /* Generate some stats about this student's performance among their peers
         */
        $statistics = (new CohortStatistics(
            $this->getViewer(),
            $this->getRoleTypeOverride(),
            $this->cohortAssessments,
            $feedback
        ))->generate();

        return $statistics;
    }


    /**
     * Returns whether we should show this component.
     *
     * Visibility derived from class assessment where student is participating
     *
     * Visible if:
     * * Assessment is visible to user (ignoring SIVO), and
     * * Assessment feedback visible to viewer's RoleType
     *
     * @param GenericCollection|DueWork\Model[] $classDueWorks
     * @return bool
     */
    private function deriveComponentVisibility(GenericCollection $classDueWorks): bool
    {
        foreach ($classDueWorks as $dueWork) {
            if (!$dueWork->isParticipant($this->student)) {
                continue;
            }
            return
                $this->isFeedbackVisibleToUser($dueWork) &&
                $this->visibility->isVisibleToUserOrChildOfUser($dueWork, $this->getViewer(), true, null, false);
        }
        return false;
    }


    /**
     * Returns the student's authoritative feedback for the assessment.
     *
     * Returns NULL if student not participating or does not have feedback
     *
     * @param GenericCollection|DueWork\Model[] $classDueWorks
     * @return IFeedback|null
     */
    private function findStudentFeedback(GenericCollection $classDueWorks): ?IFeedback
    {
        foreach ($classDueWorks as $dueWork) {
            $participant = $dueWork->getParticipantIfExtant($this->student);
            if ($participant && !$participant->isExcluded()) {
                return $participant->getAuthoritativeFeedback(true);
            }
        }
        return null;
    }
}
