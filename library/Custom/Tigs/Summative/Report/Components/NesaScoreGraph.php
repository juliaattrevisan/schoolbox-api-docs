<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Custom\Tigs\Summative\Report\StudentAttributes;
use Custom\Tigs\Summative\Report\BaseComponent;
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
use LogicException;

/**
 * Builds a suitable data structure for rendering the NESA Score Graph component.
 */
class NesaScoreGraph extends BaseComponent
{

    // Sort left-to-right for highcharts performance. @see https://www.highcharts.com/errors/15/
    private const BASE_MARKS = [
        'E' => 0,
        'D' => 0,
        'C' => 0,
        'B' => 0,
        'A' => 0,
    ];

    /** @var GenericCollection|UnitImpl\Model[] */
    private $unitImpls;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var User */
    private $viewer;

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
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        Folder\Model $folder
    ) {
        parent::__construct($attributes);

        $this->unitImpls  = $unitImpls;
        $this->student    = $student;
        $this->visibility = $visibility;
        $this->viewer     = $viewer;
        $this->workTypeId = $workTypeId;
        $this->folder     = $folder;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig.
     *
     * @return array
     * @throws LogicException
     */
    public function toContext(): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        $error       = null;
        try {
            [$markData, $studentMark] = $this->collateMarks($this->collateAssessables());
        } catch (UnexpectedValueException $e) {
            $error = $e->getMessage();
        }

        $context = [
            'data'         => $markData,
            'studentValue' => $studentMark,
        ];
        if (isset($error)) {
            $context['error'] = $error;
        }

        return $context;
    }


    /**
     * Collate the unit implementations into a set of assessables.
     *
     * @return array
     * @var Folder\Model    $assessables [$folderId]['folder']      The folder the assessables are in
     * @var DueWork\Model[] $assessables [$folderId]['assessments'] The NESA assessables for this folder
     * @throws UnexpectedValueException If NESA assessable isn't valid
     */
    private function collateAssessables(): array
    {
        $assessables = [];
        foreach ($this->unitImpls as $unitImpl) {
            // Get the NESA Score assessable out of each unit impl
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

                if (!$this->isDueWithinReportWindow($assessment)) {
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
                            'Only due works can be used for the NESA Score graph. Assessment "%s" in folder "%s" is a %s.',
                            $assessment->getTitle(),
                            $folder->getTitle(),
                            $assessment->getTypeName()
                        )
                    );
                }

                if ($assessment->getMarkType()->getSlug() !== MarkType::SLUG_LETTER) {
                    throw new UnexpectedValueException(
                        sprintf(
                            "A NESA Score due work must have Mark Type of 'Letter Grade'. Assessment '%s' in folder '%s' has a mark type of  '%s'",
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
     * @param array $assessables A set of assessments, from `$this->collateAssessments`.
     * @return array 0: all marks; [mark value => number of students with this mark]
     *                           1: the mark this student received, if any
     * @throws UnexpectedValueException if a folder has more or less than one MYP/DP assessment
     */
    private function collateMarks(array $assessables): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        foreach ($assessables as $folderId => $folderAssessments) {
            /**
             * @var Folder\Model    $folder
             * @var DueWork\Model[] $assessments
             */
            $folder      = $folderAssessments['folder'];
            $assessments = $folderAssessments['assessments'];
            $count       = \count($assessments);

            // No folder can have multiple assessments, and the current folder must have a single assessment
            if ($count > 1 || ($count === 0 && $folderId === $this->folder->getId())) {
                throw new UnexpectedValueException(
                    sprintf(
                        'A folder must have exactly one NESA Score assessment. Folder "%s" contains %d.',
                        $folder->getTitle(),
                        $count
                    )
                );
            }

            /** @var IAssessable $assessment */
            $assessment = \reset($assessments);
            if (!$assessment) {
                continue;
            }

            // Assessment not visible to the viewer? Show no results
            if (!$this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->viewer, true, null, false)) {
                continue;
            }

            // Feedback not visible to the viewer? Show no results
            if (!$assessment->isFeedbackVisibleToUser($this->viewer)) {
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

        return [$markData, $studentMark];
    }
}
