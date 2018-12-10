<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DueWork;
use UnexpectedValueException;

/**
 * Builds a suitable data structure for rendering the Diploma Programme
 * components.
 */
class DiplomaProgrammeGraph
{
    /**
     * @var GenericCollection|UnitImpl\Model[]
     */
    private $unitImpls;

    /**
     * @var User
     */
    private $student;

    /**
     * @var Visibility
     */
    private $visibility;

    /**
     * @var User
     */
    private $viewer;

    /**
     * @var int
     */
    private $dpWorkTypeId;

    /**
     * @var RubricMapper
     */
    private $rubricMapper;


    /**
     * Constructor
     *
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $dpWorkTypeId
     * @param RubricMapper      $rubricMapper
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        Visibility $visibility,
        User $viewer,
        int $dpWorkTypeId,
        RubricMapper $rubricMapper
    ) {
        $this->unitImpls    = $unitImpls;
        $this->student      = $student;
        $this->visibility   = $visibility;
        $this->viewer       = $viewer;
        $this->dpWorkTypeId = $dpWorkTypeId;
        $this->rubricMapper = $rubricMapper;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig.
     *
     * @return array
     */
    public function toContext(): array
    {
        // What do I need to generate this data set?
        // * the DP grade assessment for this student
        // * the DP grade assessments for the cohort
        // * the student themselves


        /** @var DueWork\Model[][] $dpAssessments */
        $error         = null;
        $dpAssessments = $this->collateAssessments();


        $markData = [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
        ];
        $studentMark = null;
        foreach ($dpAssessments as $folderId => $folderAssessments) {
            $count = count($folderAssessments);
            if ($count !== 1) {
                $error = "A unit must have exactly one MYP/DP assessment. Found {$count}.";
                continue;
            }

            $dpAssessment = current($folderAssessments);

            // Not visible to the viewer? Show no results
            if (!$this->visibility->isVisibleToUser($dpAssessment, $this->viewer, true)) {
                continue;
            }

            foreach ($dpAssessment->getAllParticipants() as $participant) {
                // What kind of assessment is this?
                $mark     = null;
                $feedback = $participant->getAuthoritativeFeedback(true);
                if (!$feedback) {
                    // No visible feedback for this user?
                    // Show nothing, but this is not an error
                    continue;
                }
                if ($dpAssessment->getUseRubric()) {
                    $markedGrid = $this->rubricMapper->retrieveMarkedGrid($feedback);
                    // Finagle student mark from rubric grid
                    $rawStudentMark = $markedGrid->retrieveHumanReadableMark();
                    if ($rawStudentMark === 0) {
                        $mark = 0;
                    } elseif ($rawStudentMark <= 5) {
                        $mark = 1;
                    } elseif ($rawStudentMark <= 9) {
                        $mark = 2;
                    } elseif ($rawStudentMark <= 14) {
                        $mark = 3;
                    } elseif ($rawStudentMark <= 18) {
                        $mark = 4;
                    } elseif ($rawStudentMark <= 23) {
                        $mark = 5;
                    } elseif ($rawStudentMark <= 27) {
                        $mark = 6;
                    } elseif ($rawStudentMark <= 32) {
                        $mark = 7;
                    } else {
                        $mark = null;
                    }
                } else {
                    $mark = $feedback->getHumanReadableMark();
                }

                if (isset($mark)) {
                    if ($participant->getUser()->getId() === $this->student->getId()) {
                        $studentMark = $mark;
                    }
                    $markData[$mark]++;
                }
            }
        }


        // The validation gauntlet has been passed



        // Cohort is determined by:
        // * taking the unit impl
        // * finding all assessments within it
        // * determining which one(s) are the MYP/DP work type


        // There are two types of assessment that could feed this graph:
        // MYP students: a rubric with total indicator mark of 32
        // Mapping from total rubric indicator marks to graph value:
        // 0:     0
        // 1-5:   1
        // 6-9:   2
        // 10-14: 3
        // 15-18: 4
        // 19-23: 5
        // 24-27: 6
        // 28-32: 7
        // DP students: a due work with a max mark of 7


        // Dummy data: just output counts
        return [
            'header'       => 'MYP Grade',
            'data'         => $markData,
            'studentValue' => $studentMark,
            'error'        => $error,
        ];
    }


    /**
     * Collate the unit implementations into a set of assessments.
     *
     * @return DueWork\Model[][] A list of DP assessments, indexed by folder ID
     *                           and then by assessment ID
     * @throws UnexpectedValueException if a DP assessment isn't a due work,
     *                                  and has neither a rubric or a max mark
     *                                  of 7.
     */
    private function collateAssessments(): array
    {
        $dpAssessments = [];
        foreach ($this->unitImpls as $unitImpl) {
            // Get the DP assessment out of each unit impl
            // If any impl has zero, or more than one: that's an error
            $folderId = $unitImpl->getBaseContext()->getFolder()->getId();
            if (!isset($dpAssessments[$folderId])) {
                $dpAssessments[$folderId] = [];
            }
            foreach ($unitImpl->getAssessables() as $assessment) {
                $workType = $assessment->getWorkType();
                if ($workType && $workType->getId() === $this->dpWorkTypeId) {

                    // Iterate deeper into projects

                    // Validate the assessment
                    if (!$assessment instanceof DueWork\Model) {
                        throw new UnexpectedValueException(
                            "Only due works can be used for the MYP/DP graph. Found: {$assessment->getTypeName()}."
                        );
                    }

                    // Assessment must either be:
                    // * Total mark = 7
                    // * Use rubric
                    if (!($assessment->getUseRubric()
                        || ($assessment->getMarkType()->getSlug() !== MarkType::SLUG_TOTAL && $assessment->getMaxMark() !== 7)
                    )) {
                        throw new UnexpectedValueException(
                            "A MYP/DP due work must either have a rubric, or a max mark of 7."
                        );
                    }

                    $dpAssessments[$folderId][$assessment->getId()] = $assessment;
                }
            }
        }
        return $dpAssessments;
    }
}
