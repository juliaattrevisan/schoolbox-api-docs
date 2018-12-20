<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Custom\Tigs\Summative\Report\StudentAttributes;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;
use Alaress\Authorizer\Authorizer;
use Aura\Router;
use LogicException;

/**
 * Builds a suitable data structure for rendering the MYP/DP Graph component.
 */
class InternationalBaccalaureateGraph
{
    private const BASE_MARKS = [
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

    /** @var StudentAttributes */
    private $attributes;


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
    private $workTypeId;


    /**
     * @var RubricMapper
     */
    private $rubricMapper;

    /** @var Authorizer */
    private $authorizer;

    /** @var Router\Router */
    private $router;

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
     * @param RubricMapper      $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     * @param Folder\Model      $folder
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        RubricMapper $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router,
        Folder\Model $folder
    ) {
        $this->unitImpls           = $unitImpls;
        $this->student             = $student;
        $this->attributes          = $attributes;
        $this->visibility          = $visibility;
        $this->viewer              = $viewer;
        $this->workTypeId          = $workTypeId;
        $this->rubricMapper        = $rubricMapper;
        $this->authorizer          = $authorizer;
        $this->router              = $router;
        $this->folder              = $folder;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig.
     *
     * @return array
     * @throws Router\Exception\RouteNotFound
     * @throws LogicException
     */
    public function toContext(): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        $error       = null;
        $header      = $this->generateHeader();
        try {
            [$markData, $studentMark] = $this->collateMarks($this->collateAssessments());
        } catch (UnexpectedValueException $e) {
            $error = $e->getMessage();
        } catch (MapperException $e) {
            $error = $e->getMessage();
        }

        $context = [
            'header'         => $header,
            'data'           => $markData,
            'dataCategories' => array_keys($markData),
            'studentValue'   => $studentMark,
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
     * @var Folder\Model    $dpAssessments [$folderId]['folder']      The folder the assessments are in
     * @var DueWork\Model[] $dpAssessments [$folderId]['assessments'] The MYP/DP assessments for this folder
     * @throws UnexpectedValueException if a IB Report Grade assessment isn't a due work,
     *                                  and has neither a rubric or a max mark
     *                                  of 7.
     */
    private function collateAssessments(): array
    {
        $ibAssessments = [];
        foreach ($this->unitImpls as $unitImpl) {
            // Get the DP assessment out of each unit impl
            // If any impl has zero, or more than one: that's an error
            $folder   = $unitImpl->getBaseContext()->getFolder();
            $folderId = $folder->getId();
            if (!isset($ibAssessments[$folderId])) {
                $ibAssessments[$folderId] = [
                    'folder'      => $folder,
                    'assessments' => [],
                ];
            }
            foreach ($this->retrieveAssessments($unitImpl) as $assessment) {
                $workType = $assessment->getWorkType();
                if (!$workType || $workType->getId() !== $this->workTypeId) {
                    continue;
                }

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

                if($this->attributes->isMyp()){
                    // MYP Assessment must use a rubric:
                    if(!$assessment->getUseRubric()){
                        throw new UnexpectedValueException(
                            sprintf(
                                'An MYP due work must have a rubric. Assessment "%s" in folder "%s" does not have a rubric.',
                                $assessment->getTitle(),
                                $folder->getTitle()
                            )
                        );
                    }
                } elseif($assessment->getMaxMark() !== 7.0 || $assessment->getMarkType()->getSlug() !== MarkType::SLUG_TOTAL){
                    throw new UnexpectedValueException(
                        sprintf(
                            'A DP due work must either have a max mark of 7. Assessment "%s" in folder "%s" is a %s with max mark %d.',
                            $assessment->getTitle(),
                            $folder->getTitle(),
                            $assessment->getMarkType()->getName(),
                            $assessment->getMaxMark()
                        )
                    );
                }

                $ibAssessments[$folderId]['assessments'][$assessment->getId()] = $assessment;
            }
        }
        return $ibAssessments;
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
     * @param array $ibAssessments A set of assessments, from `$this->collateAssessments`.
     * @return array 0: all marks; [mark value => number of students with this mark]
     *               1: the mark this student received, if any
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException if a folder has more or less than one MYP/DP assessment
     * @throws Router\Exception\RouteNotFound
     */
    private function collateMarks(array $ibAssessments): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        foreach ($ibAssessments as $folderId => $folderAssessments) {
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
                        'A folder must have exactly one MYP/DP assessment. Folder "%s" contains %d.',
                        $folder->getTitle(),
                        $count
                    )
                );
            }

            $assessment = current($assessments);
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

                // What kind of assessment is this?
                $mark     = null;
                $feedback = $participant->getAuthoritativeFeedback(true);
                if (!$feedback) {
                    // No visible feedback for this user?
                    // Show nothing, but this is not an error
                    continue;
                }
                if ($this->attributes->isMyp()) {
                    $markedGrid     = $this->rubricMapper->retrieveMarkedGrid($feedback);
                    $rawStudentMark = $markedGrid->calculateScaledMark();
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
                    if (preg_match('#(\d) / 7#', $mark, $matches)) {
                        $mark = (int)$matches[1];
                    } else {
                        $mark = null;
                    }
                }

                if (0 === $mark || $mark > 7) {
                    $student        = $participant->getUser();
                    $studentName    = $student->getFullName();
                    $assessmentName = $assessment->getTitle();
                    $routeName      = 'learningAssessment.getSummary';
                    $routeParams    = [
                        'assessmentId' => $assessment->getId(),
                        'assessment'   => $assessment,
                        'studentId'    => $student->getId(),
                        'student'      => $student,
                    ];
                    if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->viewer)) {
                        $route          = $this->router->generate($routeName, $routeParams);
                        $assessmentName = \sprintf('<a href="%s">%s</a>', $route, $assessmentName);
                    }
                    throw new UnexpectedValueException("Invalid mark found. $studentName has been assigned a mark of 0 in $assessmentName. Required 1-7");
                }

                if (null !== $mark) {
                    // Only care about student's mark in current folder. This caters for case where student does not yet have mark in this class, but
                    // has participation record in a different class in this cohort.
                    if ($participant->getUser()->getId() === $this->student->getId() && $this->folder->getId() === $assessment->getContext()->getFolder()->getId()) {
                        $studentMark = $mark;
                    }
                    $markData[$mark]++;
                }
            }
        }
        return [$markData, $studentMark];
    }


    /**
     * Generates a header for this graph.
     *
     * * Year 12, Sem 2: Predicted Grade
     * * Year 12, Sem 1: Early Indicator
     * * Year 11, Sem 2: Early Indicator
     * * Year 11, Sem 1: MYP Grade
     *
     * @return string
     * @throws LogicException
     */
    private function generateHeader(): string
    {
        $year     = $this->attributes->getCurriculumYearLevel();
        $semester = $this->attributes->getCurriculumSemester();

        if ($year <= 10 && $semester === 2) {
            return 'MYP Grade';
        }
        if ($year === 11) {
            return 'Early Indicator';
        }
        if ($year === 12 && $semester === 2) {
            return 'Predicted Grade';
        }
        throw new LogicException("Unable to determine header. Curriculum Year: {$year}, Curriculum Semester: {$semester}");
    }
}
