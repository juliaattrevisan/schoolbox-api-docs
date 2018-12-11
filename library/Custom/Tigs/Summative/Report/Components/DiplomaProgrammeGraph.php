<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Builds a suitable data structure for rendering the Diploma Programme
 * components.
 */
class DiplomaProgrammeGraph
{
    private const BASE_MARKS = [
        0 => 0,
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0,
        6 => 0,
        7 => 0,
    ];


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


        $markData    = self::BASE_MARKS;
        $studentMark = null;
        $error       = null;
        try {
            [$markData, $studentMark] = $this->collateMarks($this->collateAssessments());
        } catch (UnexpectedValueException $e) {
            $error = $e->getMessage();
        } catch (MapperException $e) {
            $error = $e->getMessage();
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
        $context = [
            'header'       => 'MYP Grade',
            'data'         => $markData,
            'studentValue' => $studentMark,
        ];
        if (isset($error)) {
            $context['error'] = $error;
        }
        return $context;
    }


    /**
     * Collate the unit implementations into a set of assessments.
     *
     * @return array
     * @var Folder          $dpAssessments [$folderId]['folder']      The folder the assessments are in
     * @var DueWork\Model[] $dpAssessments [$folderId]['assessments'] The MYP/DP assessments for this folder
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
            $folder   = $unitImpl->getBaseContext()->getFolder();
            $folderId = $folder->getId();
            if (!isset($dpAssessments[$folderId])) {
                $dpAssessments[$folderId] = [
                    'folder'      => $folder,
                    'assessments' => [],
                ];
            }
            foreach ($this->retrieveAssessments($unitImpl) as $assessment) {
                $workType = $assessment->getWorkType();
                if ($workType && $workType->getId() === $this->dpWorkTypeId) {

                    // Iterate deeper into projects

                    // Validate the assessment
                    if (!$assessment instanceof DueWork\Model) {
                        throw new UnexpectedValueException(
                            sprintf(
                                'Only due works can be used for the MYP/DP graph. Assessment "%s" in folder "%s" is a %s.',
                                $assessment->getTitle(),
                                $folder->getTitle(),
                                $assessment->getTypeName()
                            )
                        );
                    }

                    // Assessment must either be:
                    // * Total mark = 7
                    // * Use rubric
                    if (!($assessment->getUseRubric()
                        || ($assessment->getMarkType()->getSlug() !== MarkType::SLUG_TOTAL && $assessment->getMaxMark() !== 7)
                    )) {
                        throw new UnexpectedValueException(
                            sprintf(
                                'A MYP/DP due work must either have a rubric, or a max mark of 7. Assessment "%s" in folder "%s" is a %s with max mark %d.',
                                $assessment->getTitle(),
                                $folder->getTitle(),
                                $assessment->getMarkType()->getName(),
                                $assessment->getMaxMark()
                            )
                        );
                    }

                    $dpAssessments[$folderId]['assessments'][$assessment->getId()] = $assessment;
                }
            }
        }
        return $dpAssessments;
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
     * @param array $dpAssessments A set of assessments, from `$this->collateAssessments`.
     * @return array 0: all marks; [mark value => number of students with this mark]
     *               1: the mark this student received, if any
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException if a folder has more or less than one DP assessment
     */
    private function collateMarks(array $dpAssessments): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        foreach ($dpAssessments as $folderId => $folderAssessments) {
            /**
             * @var Folder          $folder
             * @var DueWork\Model[] $assessments
             */
            $folder      = $folderAssessments['folder'];
            $assessments = $folderAssessments['assessments'];
            $count       = count($assessments);
            if ($count !== 1) {
                throw new UnexpectedValueException(
                    sprintf(
                        'A folder must have exactly one MYP/DP assessment. Folder "%s" contains %d.',
                        $folder->getTitle(),
                        $count
                    )
                );
            }

            $dpAssessment = current($assessments);

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
                    $markedGrid     = $this->rubricMapper->retrieveMarkedGrid($feedback);
                    $rawStudentMark = $markedGrid->retrieveHumanReadableMark();
                    // Finagle student mark from rubric grid
                    if (!isset($rawStudentMark)) {
                        $mark = null;
                    } elseif ($rawStudentMark === 0) {
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
        return [$markData, $studentMark];
    }
}
