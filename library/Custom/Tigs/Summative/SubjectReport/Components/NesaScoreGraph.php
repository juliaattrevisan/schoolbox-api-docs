<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Builds a suitable data structure for rendering the NESA Grade Graph component.
 */
class NesaScoreGraph extends BaseComponent
{

    // Sort left-to-right for highcharts performance. @see https://www.highcharts.com/errors/15/
    private const BASE_MARKS = [
        'A' => 0,
        'B' => 0,
        'C' => 0,
        'D' => 0,
        'E' => 0,
    ];

    /** @var GenericCollection|UnitImpl\Model[] */
    private $unitImpls;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var int */
    private $workTypeId;

    /** @var Folder\Model */
    private $folder;


    /**
     * Constructor
     *
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @param StudentAttributes $attributes
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $workTypeId
     * @param Folder\Model      $folder
     * @param string|null       $roleTypeOverride
     * @throws \InvalidArgumentException
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        Folder\Model $folder,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);

        $this->unitImpls  = $unitImpls;
        $this->student    = $student;
        $this->visibility = $visibility;
        $this->workTypeId = $workTypeId;
        $this->folder     = $folder;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig.
     *
     * @param int $semester
     * @return array
     */
    public function toContext(int $semester): ?array
    {
        try {
            [$markData, $studentMark, $showComponent, $comment] = $this->collateMarks($this->collateAssessables($semester));
            return $showComponent
                ? [
                    'cohortMarks'   => $markData,
                    'studentMark'   => $studentMark,
                    'semester'      => $semester,
                    'comment'       => $comment,
                ]
                : null;
        } catch (UnexpectedValueException $e) {
            $error = $e->getMessage();
        }

        return $this->showErrors() ? ['error' => $error] : null;
    }


    /**
     * Collate the unit implementations into a set of assessables.
     *
     * @param int $semester
     * @return array
     * @internal Folder\Model    $assessables [$folderId]['folder']      The folder the assessables are in
     * @internal DueWork\Model[] $assessables [$folderId]['assessments'] The NESA assessables for this folder
     * @throws UnexpectedValueException If NESA assessable isn't valid
     */
    private function collateAssessables(int $semester): array
    {
        $assessables = [];
        foreach ($this->unitImpls as $unitImpl) {
            // Get the NESA Grade assessable out of each unit impl
            // We only care about assessments due within the report's window
            $folder   = $unitImpl->getBaseContext()->getFolder();
            $folderId = $folder->getId();
            if (!isset($assessables[$folderId])) {
                $assessables[$folderId] = [
                    'folder'      => $folder,
                    'assessments' => [],
                ];
            }
            foreach ($this->retrieveAssessments($unitImpl) as $assessment) {

                if (!$this->isDueWithinReportWindow($assessment, $semester)) {
                    continue;
                }

                $workType = $assessment->getWorkType();
                if (!$workType || $workType->getId() !== $this->workTypeId) {
                    continue;
                }

                // Validate the assessment
                if (!$assessment instanceof DueWork\Model) {
                    throw new UnexpectedValueException(
                        sprintf(
                            'Only due works can be used for the NESA Grade graph. Assessment "%s" in folder "%s" is a %s.',
                            $assessment->getTitle(),
                            $folder->getTitle(),
                            $assessment->getTypeName()
                        )
                    );
                }

                if ($assessment->getMarkType()->getSlug() !== MarkType::SLUG_LETTER) {
                    throw new UnexpectedValueException(
                        sprintf(
                            "A NESA Grade due work must have Mark Type of 'Letter Grade'. Assessment '%s' in folder '%s' has a mark type of  '%s'",
                            $assessment->getTitle(),
                            $folder->getTitle(),
                            $assessment->getMarkType()->getName()
                        )
                    );
                }

                $assessables[$folderId]['assessments'][$assessment->getId()] = $assessment;
            }
        }

        return $assessables;
    }


    /**
     * Helper function for retrieving a list of assessments out of a context.
     *
     * @param AssessmentContext $context
     * @return iterable|IAssessable[]
     */
    private function retrieveAssessments(AssessmentContext $context): iterable
    {
        foreach ($context->getAssessables() as $assessment) {
            if ($assessment instanceof AssessmentContext) {
                yield from $this->retrieveAssessments($assessment);
            } else {
                yield $assessment;
            }
        }
    }


    /**
     * Collates marks, given a set of MYP/DP assessments.
     *
     * @param array         $assessmentData A set of assessments, from `$this->collateAssessments`.
     * @var Folder\Model    $assessmentData [folderId]['folder']
     * @var DueWork\Model[] $assessmentData [folderId]['assessments']
     * @return array 0: all marks; [mark value => number of students with this mark]
     *               1: the mark this student received, if any
     *               2: should we show the component at all?
     * @throws UnexpectedValueException if a folder has more or less than one MYP/DP assessment
     */
    private function collateMarks(array $assessmentData): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        $comment = [];

        $this->validateAssessments($assessmentData);
        $showComponent = $this->deriveComponentVisibility($assessmentData);

        foreach ($assessmentData as $folderId => $folderData) {
            if (!$showComponent) {
                continue;
            }
            /** @var DueWork\Model $assessment */
            foreach ($folderData['assessments'] as $assessment) {

                // Feedback not visible to the viewer? Do not collate marks
                if (!$this->isFeedbackVisibleToUser($assessment)) {
                    continue;
                }

                foreach ($assessment->getAllParticipants() as $participant) {
                    $feedback = $participant->getAuthoritativeFeedback(true);
                    if (!$feedback) {
                        // No visible feedback for this user?
                        // Show nothing, but this is not an error
                        continue;
                    }

                    $humanReadableMark = $feedback->getHumanReadableMark();

                    // Only care about student's mark in current folder. This caters for case where student does not yet have mark in this class, but
                    // has participation record in a different class in this cohort.
                    if ($participant->getUser()->getId() === $this->student->getId() && $this->folder->getId() === $assessment->getContext()->getFolder()->getId()) {
                        $studentMark = $humanReadableMark;

                        // MYP students' comments will come from this assessment
                        if ($this->getAttributes()->isMyp()) {
                            $comment['comment']   = $feedback->getComment();
                            $comment['commentBy'] = $feedback->getReviewer();
                        }
                    }

                    // It's quite possible for students to be given marks outside valid range, eg HR = 'Absent', Norm = 0.
                    // Since we cannot predict all possible marks, and whether these should be ignored or considered an error,
                    // we will simply ignore all invalid data.
                    if (!\preg_match('#^[ABCDE]$#', $humanReadableMark, $matches)) {
                        continue;
                    }

                    $markData[$humanReadableMark]++;
                }
            }
        }

        return [$markData, $studentMark, $showComponent, $comment];
    }


    /**
     * Ensures the assessments have been configured correctly
     * * Cohort students may only participate in 0:1 NESA assessment per Folder per ReportPeriod
     * * This class must have at least 1 NESA assessment
     *
     * @param array         $assessmentData
     * @throws UnexpectedValueException If invalid data detected
     * @var Folder\Model    $assessmentData [folderId]['folder']
     * @var DueWork\Model[] $assessmentData [folderId]['assessments']
     * @var array           $assessmentData [folderId]
     */
    private function validateAssessments(array $assessmentData): void
    {
        // Check students only participate in no more than 1 NESA assessment per Folder per ReportPeriod
        // (Note we are already in the context of a single ReportPeriod)
        /** @var int[][] $participationCountsPerFolder Indexed by folderId, studentId */
        $participationCountsPerFolder = [];
        foreach ($assessmentData as $folderId => $folderData) {
            $participationCountsPerFolder[$folderId] = [];
            /** @var DueWork\Model[] $cohortClassAssessments */
            $cohortClassAssessments = $folderData['assessments'];
            foreach ($cohortClassAssessments as $cohortClassAssessment) {
                foreach ($cohortClassAssessment->getAllParticipants() as $participant) {
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

        // Build participation count error messages
        $participationCountErrors = [];
        foreach ($participationCountsPerFolder as $folderId => $participationCounts) {
            $folder          = $assessmentData[$folderId]['folder'];
            $folderLink      = sprintf('<a href="/homepage/%d">%s</a>', $folder->getId(), $folder->getTitle());
            foreach ($participationCounts as $studentId => $count) {
                if ($count >= 2) {
                    $participationCountErrors[] = "$folderLink: Student: $studentId, Count: $count";
                }
            }
        }
        $errorMessage = '';
        if (count($participationCountErrors)) {
            $errorMessage .= "Invalid data. Students may only participate in 1 <strong>NESA Graph</strong> assessment per class per report period.<br>\n";
            $errorMessage .= implode("<br>\n", $participationCountErrors);
        }

        // Check this folder has at least 1 NESA assessment
        if (!count($assessmentData[$this->folder->getId()]['assessments'])) {
            $folderLink   = sprintf('<a href="/homepage/%d">%s</a>', $this->folder->getId(), $this->folder->getName());
            $errorMessage .= "No NESA Graph assessment found for folder $folderLink\n";
        }
        if ($errorMessage) {
            throw new UnexpectedValueException($errorMessage);
        }
    }


    /**
     * Returns whether we should render the component.
     *
     * Component visibility is derived from the assessment which:
     * * belongs to the current class, and
     * * where the student is participating
     *
     * Component is visible if:
     * * Assessment is visible (ignoring SIVO), and
     * * Assessment feedback is visible to user
     *
     * @param array         $assessmentData
     * @return bool
     * @var Folder\Model    $assessmentData [folderId]['folder']
     * @var DueWork\Model[] $assessmentData [folderId]['assessments']
     * @var array           $assessmentData [folderId]
     */
    private function deriveComponentVisibility(array $assessmentData): bool
    {
        /** @var DueWork\Model $assessment */
        foreach ($assessmentData[$this->folder->getId()]['assessments'] ?? [] as $assessment) {
            if (!$assessment->isParticipant($this->student)) {
                continue;
            }

            return
                $this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->getViewer(), true, null, false) &&
                $this->isFeedbackVisibleToUser($assessment);
        }
        return false;
    }
}
