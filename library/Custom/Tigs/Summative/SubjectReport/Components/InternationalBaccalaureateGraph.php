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
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
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
class InternationalBaccalaureateGraph extends BaseComponent
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


    /**
     * @var Visibility
     */
    private $visibility;


    /**
     * @var int
     */
    private $workTypeId;


    /**
     * @var RubricDataSource
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
     * @param RubricDataSource  $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     * @param Folder\Model      $folder
     * @param string|null       $roleTypeOverride
     * @throws InvalidArgumentException
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        RubricDataSource $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router,
        Folder\Model $folder,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);

        $this->unitImpls           = $unitImpls;
        $this->student             = $student;
        $this->visibility          = $visibility;
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
        $studentComment = null;
        $error       = null;
        $header      = $this->generateHeader();
        $showComponent = true; // If exceptions occur, we need to show component so we can see those errors
        try {
            [$markData, $studentMark, $studentComment, $showComponent] = $this->collateMarks($this->collateAssessments());
        } catch (UnexpectedValueException $e) {
            $error = $e->getMessage();
        } catch (MapperException $e) {
            $error = $e->getMessage();
        }

        $context = [
            'header'         => $header,
            'data'           => $markData,
            'studentMark'    => $studentMark,
            'studentComment' => $studentComment,
            'useComment'     => $this->getAttributes()->isDp(),
            'showComponent'  => $showComponent,
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
            // Get the IB assessment out of each unit impl
            // We only care about assessments due within the report's window
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

                if (!$this->isDueWithinReportWindow($assessment)) {
                    continue;
                }

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

                if ($this->getAttributes()->isMyp()) {
                    // MYP Assessment must use a rubric:
                    if (!$assessment->getUseRubric()) {
                        throw new UnexpectedValueException(
                            sprintf(
                                'An MYP due work must have a rubric. Assessment "%s" in folder "%s" does not have a rubric.',
                                $assessment->getTitle(),
                                $folder->getTitle()
                            )
                        );
                    }
                } elseif ($assessment->getMarkType()->getSlug() !== MarkType::SLUG_LETTER) {
                    throw new UnexpectedValueException(
                        sprintf(
                            "A DP due work must have Mark Type of 'Letter Grade'. Assessment '%s' in folder '%s' has a mark type of  '%s'",
                            $assessment->getTitle(),
                            $folder->getTitle(),
                            $assessment->getMarkType()->getName()
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
     *               2: the comment this student received, if any
     *               3: Whether we should show this component at all
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException If a invalid data found
     * @throws Router\Exception\RouteNotFound
     */
    private function collateMarks(array $ibAssessments): array
    {
        $markData    = self::BASE_MARKS;
        $studentMark = null;
        $studentComment = null;

        $this->validateAssessments($ibAssessments);
        $showComponent = $this->deriveComponentVisibility($ibAssessments);

        foreach ($ibAssessments as $folderId => $folderData) {
            if (!$showComponent) {
                continue;
            }
            /** @var DueWork\Model $assessment */
            foreach ($folderData['assessments'] as $assessment) {

                // Ignores marks from assessments where grades not visible to viewer's RoleType
                if (!$this->isFeedbackVisibleToUser($assessment)) {
                    continue;
                }

                // Collate marks
                foreach ($assessment->getAllParticipants() as $participant) {

                    // What kind of assessment is this?
                    $mark     = null;
                    $feedback = $participant->getAuthoritativeFeedback(true);
                    if (!$feedback) {
                        // No visible feedback for this user?
                        // Show nothing, but this is not an error
                        continue;
                    }
                    if ($this->getAttributes()->isMyp()) {
                        $markedGrid     = $this->rubricMapper->retrieveMarkedGrid($feedback);
                        $rawStudentMark = $markedGrid->calculateScaledMark();
                        // Finagle student mark from rubric grid
                        if ($rawStudentMark === 0.0) {
                            // The call to calculateScaledMark() currently returns 0.0 for an unmarked rubric. It should probably return NULL
                            // so we can distinguish between an unmarked rubric, and a rubric with a mark of 0.0. However that change is pretty deep.
                            // Instead, since we know this rubric cannot have a mark of 0 (because all descriptor values are 1-8), we'll just compensate
                            // for it here by assuming this means the rubric is unmarked.
                            $mark = null;
                        } elseif ($rawStudentMark <= 5.0) {
                            $mark = 1;
                        } elseif ($rawStudentMark <= 9.0) {
                            $mark = 2;
                        } elseif ($rawStudentMark <= 14.0) {
                            $mark = 3;
                        } elseif ($rawStudentMark <= 18.0) {
                            $mark = 4;
                        } elseif ($rawStudentMark <= 23.0) {
                            $mark = 5;
                        } elseif ($rawStudentMark <= 27.0) {
                            $mark = 6;
                        } elseif ($rawStudentMark <= 32.0) {
                            $mark = 7;
                        } else {
                            throw $this->generateException($participant, "Invalid rubric total: {$rawStudentMark}");
                        }
                    } else {
                        $mark = $feedback->getHumanReadableMark();
                        if (preg_match('#(\d)#', $mark, $matches)) {
                            $mark = (int)$matches[1];
                        } else {
                            $mark = null;
                        }
                    }

                    // Only care about student's mark in current folder. This caters for case where student does not yet have mark in this class, but
                    // has participation record in a different class in this cohort.
                    if ($participant->getUser()->getId() === $this->student->getId() && $this->folder->getId() === $assessment->getContext()->getFolder()->getId()) {
                        $studentMark    = $mark;
                        $studentComment = $feedback->getComment();
                    }

                    // NULL marks do not contribute
                    if (null === $mark) {
                        continue;
                    }

                    // Ensure mark is valid
                    if (0 === $mark || $mark > 7) {
                        throw $this->generateException($participant, "Invalid mark found: {$mark}. Required 1-7");
                    }

                    $markData[$mark]++;
                }
            }
        }
        return [$markData, $studentMark, $studentComment, $showComponent];
    }


    /**
     * Ensures the assessments have been configured correctly
     * * Cohort students may only participate in 0:1 IB assessment per Folder per ReportPeriod
     * * This class must have at least 1 IB assessment
     *
     * @param array         $ibAssessments
     * @throws UnexpectedValueException If invalid data detected
     * @var Folder\Model    $ibAssessments [folderId]['folder']
     * @var DueWork\Model[] $ibAssessments [folderId]['assessments']
     * @var array           $ibAssessments [folderId]
     */
    private function validateAssessments(array $ibAssessments): void
    {
        // Check students only participate in 1 IB assessment per Folder per ReportPeriod
        // (Note we are already in the context of a single ReportPeriod)
        /** @var int[][] $participationCountsPerFolder Indexed by folderId, studentId */
        $participationCountsPerFolder = [];
        foreach ($ibAssessments as $folderId => $folderData) {
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
            $folder          = $ibAssessments[$folderId]['folder'];
            $folderLink      = sprintf('<a href="/homepage/%d">%s</a>', $folder->getId(), $folder->getTitle());
            foreach ($participationCounts as $studentId => $count) {
                if ($count >= 2) {
                    $participationCountErrors[] = "$folderLink: Student: $studentId, Count: $count";
                }
            }
        }

        $errorMessage = '';
        if (count($participationCountErrors)) {
            $errorMessage .= "Invalid data. Students may only participate in 1 <strong>IB Graph</strong> assessment per class per report period.<br>\n";
            $errorMessage .= implode("<br>\n", $participationCountErrors);
        }

        // Check this folder has at least 1 IB Graph assessment
         if (!count($ibAssessments[$this->folder->getId()]['assessments'])) {
            $folderLink   = sprintf('<a href="/homepage/%d">%s</a>', $this->folder->getId(), $this->folder->getName());
            $errorMessage .= "No IB Graph assessment found for folder $folderLink\n";
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
     * @param array         $ibAssessments
     * @return bool
     * @var Folder\Model    $ibAssessments [folderId]['folder']
     * @var DueWork\Model[] $ibAssessments [folderId]['assessments']
     * @var array           $ibAssessments [folderId]
     */
    private function deriveComponentVisibility(array $ibAssessments): bool
    {
        /** @var DueWork\Model $assessment */
        foreach ($ibAssessments[$this->folder->getId()]['assessments'] ?? [] as $assessment) {
            if (!$assessment->isParticipant($this->student)) {
                continue;
            }
            return $this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->getViewer(), true, null, false);
        }
        return false;
    }


    /**
     * Creates and returns an UnexpectedValueException with a message that links to
     * the student's summary page if authorised
     *
     * @param Participant $participant
     * @param string      $message
     * @return UnexpectedValueException
     * @throws Router\Exception\RouteNotFound
     */
    private function generateException(Participant $participant, string $message): UnexpectedValueException
    {
        $assessment     = $participant->getAssessable();
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
        if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->getViewer())) {
            $route          = $this->router->generate($routeName, $routeParams);
            /** @noinspection HtmlUnknownTarget */
            $assessmentName = \sprintf('<a href="%s">%s</a>', $route, $assessmentName);
        }
        return new UnexpectedValueException("Invalid data found for $studentName in $assessmentName. $message");
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
        $year     = $this->getAttributes()->getCurriculumYearLevel();

        if ($year <= 10 && $this->semesterFrom === 2) {
            return 'MYP Grade';
        }
        if (($year === 11 && $this->semesterFrom === 2) || ($year === 12 && $this->semesterFrom === 1)) {
            return 'Early Indicator';
        }
        if ($year === 12 && $this->semesterFrom === 2) {
            return 'Predicted Grade';
        }
        throw new LogicException("Unable to determine header. Curriculum Year: {$year}, Curriculum Semester: {$this->semesterFrom}");
    }
}
