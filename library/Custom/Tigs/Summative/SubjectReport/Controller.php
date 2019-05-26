<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate;
use Alaress\Web;
use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\Components\AcademicAchievementMatrix;
use Custom\Tigs\Summative\SubjectReport\Components\AcademicAchievementSummary;
use Custom\Tigs\Summative\SubjectReport\Components\FinalAchievementLevels;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentRank;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentTasks;
use Custom\Tigs\Summative\SubjectReport\Components\InternationalBaccalaureateGraph;
use Custom\Tigs\Summative\SubjectReport\Components\NesaScoreGraph;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use UnexpectedValueException;
use Schoolbox\Filter\Filters;
use PDO;
use Schoolbox\Resource\Textbox;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface */
    private $externalUser;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricMapper */
    private $rubricMapper;

    /** @var Textbox\Mapper */
    private $textBoxMapper;

    /** @var Visibility */
    private $visibility;

    /** @var IConnector */
    private $database;


    /**
     * @param External_UserInterface $externalUser
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricMapper           $rubricMapper
     * @param Textbox\Mapper         $textboxMapper
     * @param Visibility             $visibility
     * @param IConnector             $database
     */
    public function __construct(
        External_UserInterface $externalUser,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricMapper $rubricMapper,
        Textbox\Mapper $textboxMapper,
        Visibility $visibility,
        IConnector $database
    ) {
        $this->externalUser          = $externalUser;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->textBoxMapper         = $textboxMapper;
        $this->visibility            = $visibility;
        $this->database              = $database;
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/subject/user/{userId}/folder/{folderId}?now={time}
     *            Where {time} is anything recognised by strtotime()
     * @level 18.1.0
     * @param User         $user
     * @param Folder\Model $folder
     * @param string|null  $now
     * @throws ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \UnderflowException
     */
    public function getReport(User $user, Folder\Model $folder, ?string $now): void
    {
        $attributes = new StudentAttributes(
            $this->getConfigLoader(),
            $user,
            $this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'],
            $now
        );
        $roleTypeOverride = $this->request->query->get('role-type');

        $errors = [];

        $courseImplsWithUnits = $this->courseImplMapper->findByFolderId($folder->getId());

        // Check that multiple course specs have not been imported to this page
        $courseSpecs = $this->getImportedCourseSpecs($courseImplsWithUnits);
        if (1 !== \count($courseSpecs)) {
            // Don't bother loading model graph if we have 0 or multiple courses imported to this folder.
            // We can't render the page in this case because we rely on a single course-textbox being available.
            // It may be ok to import the same courseSpec multiple times, because each import may be a discrete subset of units.
            $error = \sprintf('There must be exactly 1 course imported to the class. Found %d.', $courseSpecs->count());
            $route = $this->getRouteIfAuthorized('courseImport.getManagement', ['folderId' => $folder->getId(), 'folder' => $folder]);
            if ($route) {
                /** @noinspection HtmlUnknownTarget */
                $error .= \sprintf(' <a href="%s">Manage Course imports</a>', $route);
            }
            $errors[]  = $error;
            $unitSpecsWithData = $aamData = $aasData = $hscTaskMarks = [];
            $falData   = $ibGraphData = $nesaGraphData = $courseSpec = $hscRankData = $courseDescription = null;
        } else {
            $this->loadParticipation($courseImplsWithUnits);
            $unitSpecs = $this->getUnitSpecs($courseImplsWithUnits);

            /** @var CourseSpec\Model $courseSpec */
            $courseSpec = $courseSpecs->first();

            // Build component data
            $courseDescription = $this->buildCourseDescription($courseSpec);
            $ibGraphData   = $this->buildInternationalBaccalaureateGraphData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $nesaGraphData = $this->buildNesaScoreGraphData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $falData       = $this->buildFinalAchievementLevelsData($folder, $attributes, $unitSpecs, $user, $roleTypeOverride);
            $aamData       = $this->buildAcademicAchievementMatrixData($folder, $attributes, $unitSpecs, $user, $roleTypeOverride);
            $aasData       = $this->buildAcademicAchievementSummaryData($folder, $attributes, $unitSpecs, $user, $roleTypeOverride);
            $hscTaskMarks  = $this->buildHscTaskMarksData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $hscRankData   = $this->buildHscAssessmentRankData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);

            // Filter out unit specs without any data
            $unitSpecsWithData = new Collection();
            foreach ($unitSpecs as $unitSpecId => $unitSpec) {
                // We do need to keep data for units that contain error messages, but which lack dates
                $hasData =
                    isset($aasData['data'][$unitSpecId]) ||
                    isset($aamData['data'][$unitSpecId]) ||
                    isset($hscTaskMarks['data'][$unitSpecId]);
                if (!$hasData) {
                    continue;
                }

                /**
                 * @var Alaress_Date_Format|null $latestDueDate
                 * @var Alaress_Date_Format|null $dueDateAas
                 * @var Alaress_Date_Format|null $dueDateAam
                 * @var Alaress_Date_Format|null $dueDateHsc
                 */
                $latestDueDate = null;
                $dueDateAas    = $aasData['data'][$unitSpecId]['dueDate'] ?? null;
                $dueDateAam    = $aamData['data'][$unitSpecId]['dueDate'] ?? null;
                $dueDateHsc    = $hscTaskMarks['data'][$unitSpecId]['dueDate'] ?? null;

                /** @var Alaress_Date_Format|null $dueDate */
                foreach ([$dueDateAas, $dueDateAam, $dueDateHsc] as $dueDate) {
                    if ($dueDate && (!$latestDueDate || $dueDate->gt($latestDueDate))) {
                        $latestDueDate = $dueDate;
                    }
                }

                $unitSpecsWithData->add(['id' => $unitSpecId, 'dueDate' => $latestDueDate, 'title' => $unitSpec->getTitle()]);
            }

            // Sort the unit specs by date
            // If we were unable to find a due-date, order this unit first to give prominence to it
            $unitSpecsWithData->sortByCallback(
                static function (array $a, array $b): int {
                    /** @var Alaress_Date_Format|null $dueDateA */
                    $dueDateA = $a['dueDate'];
                    /** @var Alaress_Date_Format|null $dueDateB */
                    $dueDateB = $b['dueDate'];

                    if (!$dueDateA) {
                        return $dueDateB ? -1 : 0;
                    }
                    return $dueDateB ? $dueDateA->compare($dueDateB) : +1;
                }
            );
        }

        $data   = [
            'user'          => $user,
            'folder'        => $folder,
            'teachers'      => $folder->loadTeachers()->getTeachers(),
            'courseSpec'    => $courseSpec,
            'isEnabled'     => $this->getConfigLoader()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED),
            'showNoLongerEnrolledAlert' => $now === null && !$this->isCurrentEnrollment($user, $folder),
            'attributes'    => $attributes,
            'errors'        => $errors,
            'unitSpecs'     => $unitSpecsWithData,
            'courseDescription' => $courseDescription,
            'falData'       => $falData,
            'aamData'       => $aamData,
            'aasData'       => $aasData,
            'ibGraphData'   => $ibGraphData,
            'nesaGraphData' => $nesaGraphData,
            'hscTaskMarks'  => $hscTaskMarks,
            'hscRankData'   => $hscRankData,
            'showErrors'    => ($roleTypeOverride ?? $this->getUser()->getRole()->getType()) === Role::STAFF,
        ];
        $output = $this->buildHtmlOutput('@tigsSubjectReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Returns whether this folder is a current enrollment
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     */
    private function isCurrentEnrollment(User $user, Folder\Model $folder): bool
    {
        foreach ($this->externalUser->getSubjectList($user) as $currentSubject) {
            foreach ($folder->getCodes() as $code) {
                if ($code->getCode() === $currentSubject['code']) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Returns all course implementations imported to the specified folder.
     *
     * Also loads units, assessments. and participation for all users
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImplsWithUnits
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function loadParticipation(GenericCollection $courseImplsWithUnits): void
    {
        // Load assessments + participations
        // $unitImpls stores references to unit implementation objects, which
        // are also in $courseImplsWithUnits.
        /** @var UnitImpl\Model[] $unitImpls */
        $unitImpls = new GenericCollection(UnitImpl\Model::class);
        foreach ($courseImplsWithUnits as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImpls[$unitImpl->getId()] = $unitImpl;
            }
        }

        $this->loadAssessmentsIntoUnitImpls($unitImpls);
    }


    /**
     * Returns the course specs the course impls were imported from
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImplsWithUnits
     * @return GenericCollection|CourseSpec\Model[]
     * @throws UnexpectedValueException
     */
    private function getImportedCourseSpecs(GenericCollection $courseImplsWithUnits): GenericCollection
    {
        $courseSpecs = new GenericCollection(CourseSpec\Model::class);
        foreach ($courseImplsWithUnits as $courseImpl) {
            $spec                        = $courseImpl->getSpecification();
            $courseSpecs[$spec->getId()] = $spec;
        }
        return $courseSpecs;
    }


    /**
     * Gets a list of all Units Specifications to render, sorted by unit title
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImports
     * @return GenericCollection|UnitSpec\Model[]
     * @throws UnexpectedValueException
     */
    private function getUnitSpecs(GenericCollection $courseImports): GenericCollection
    {
        $unitSpecs = new GenericCollection(UnitSpec\Model::class);

        foreach ($courseImports as $courseImport) {
            foreach ($courseImport->getUnits() as $unitImpl) {
                $unitSpec = $unitImpl->getSpecification();
                $unitSpecs[$unitSpec->getId()] = $unitSpec;
            }
        }

        return $unitSpecs->sortByCallback(function (UnitSpec\Model $left, UnitSpec\Model $right) {
            return \strnatcasecmp($left->getTitle(), $right->getTitle());
        }, true);
    }


    /**
     * Find all the cohort unit implementations of these unit implementations.
     *
     * @param CourseSpec\Model $courseSpec
     * @return GenericCollection|UnitImpl\Model[]
     * @throws UnexpectedValueException
     */
    private function findCohortUnitImpls(CourseSpec\Model $courseSpec): GenericCollection
    {
        $cohortUnitImpls = new GenericCollection(UnitImpl\Model::class);
        foreach ($this->courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId(), [], true) as $courseImport) {
            foreach ($courseImport->getUnits() as $unitImpl) {
                $cohortUnitImpls[$unitImpl->getId()] = $unitImpl;
            }
        }
        return $cohortUnitImpls;
    }


    /**
     * Builds data for rendering the AAM components.
     *
     * @param Folder\Model                       $folder
     * @param StudentAttributes                  $attributes
     * @param GenericCollection|UnitSpec\Model[] $unitSpecs
     * @param User                               $student
     * @param string|null                        $roleTypeOverride
     * @return array
     * @throws InvalidArgumentException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAcademicAchievementMatrixData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        ?string $roleTypeOverride
    ): array {
        $configName = Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX;

        try {
            $workTypeId = $this->checkWorkType(
                $configName,
                'Cannot render Approaches to Learning components. Work type not set.',
                'Set Approaches to Learning Work Type'
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        $aam = new AcademicAchievementMatrix(
            $attributes,
            $unitSpecs,
            $student,
            $folder,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter(),
            $roleTypeOverride
        );
        $aam->setSemesters(1, $attributes->getCurriculumSemester());

        return $aam->toContext();
    }


    /**
     * Builds data for rendering the AAS components.
     *
     * @param Folder\Model                       $folder
     * @param StudentAttributes                  $attributes
     * @param GenericCollection|UnitSpec\Model[] $unitSpecs
     * @param User                               $student
     * @param string|null                        $roleTypeOverride
     * @return array
     * @throws InvalidArgumentException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAcademicAchievementSummaryData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        ?string $roleTypeOverride
    ): array {
        // Only relevant for MYP students
        if (!$attributes->isMyp()) {
            return [];
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY,
                'Cannot render Academic Achievement Summary components. Work type not set.',
                'Set Academic Achievement Summary Work Type'
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        $aas = new AcademicAchievementSummary(
            $attributes,
            $unitSpecs,
            $student,
            $folder,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter(),
            $roleTypeOverride
        );
        $aas->setSemesters(1, $attributes->getCurriculumSemester());

        return $aas->toContext();
    }


    /**
     * Build data for the MYP-FAL component.
     *
     * @param Folder\Model                       $folder
     * @param StudentAttributes                  $attributes
     * @param GenericCollection|UnitSpec\Model[] $unitSpecs
     * @param User                               $student
     * @param string|null                        $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildFinalAchievementLevelsData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for MYP students in Curriculum Semester 2
        if (!$attributes->isMyp() || $attributes->getCurriculumSemester() !== 2) {
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS,
                'Cannot render Final Achievement Levels component. Work type not set.',
                'Set MYP Final Achievement Levels Work Type'
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        $fal = new FinalAchievementLevels(
            $attributes,
            $unitSpecs,
            $student,
            $folder,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter(),
            $roleTypeOverride
        );

        return $fal->toContext();
    }


    /**
     * Returns the body of a textbox with a specific heading on the CourseSpec's homepage
     *
     * @param CourseSpec\Model $courseSpec
     * @return string|null
     * @throws Predicate\InvalidColumnException
     */
    private function buildCourseDescription(CourseSpec\Model $courseSpec): ?string
    {
        $textboxHeading = $this->getConfigLoader()->get(Config::_TEXTBOX_HEADING);
        if (null === $textboxHeading) {
            return null;
        }
        $pb = $this->textBoxMapper->getPredicateBuilder()
                                  ->equals('categoryId', $courseSpec->getFolder()->getId())
                                  ->equals('heading', $textboxHeading);

        $textBoxes = $this->textBoxMapper->findByPredicates($pb);

        if (1 !== $textBoxes->count()) {
            return null;
        }
        /** @var Textbox\Model $textbox */
        $textbox = $textBoxes->first();

        return $textbox->getBody();
    }


    /**
     * Builds IB Grade Graph report data.
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return null|array
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildInternationalBaccalaureateGraphData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for:
        // * MYP-S2
        // * DP-Y11-S2, DP-Y12-S1 + DP-Y12-S2
        $yearLevel = $attributes->getCurriculumYearLevel();
        $semester  = $attributes->getCurriculumSemester();
        $semesters = [$semester];
        if (12 === $yearLevel && 2 === $semester && $attributes->isDp()) {
            $semesters = [2, 1];
        }

        // Find relevant assessments and participation and insert into units
        $reports = [];
        foreach ($semesters as $reportSemester) {
            if (2 === $reportSemester && $attributes->isMyp()) {
                $configName   = Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS;
                $errorMsg     = 'Cannot render MYP Report components. Work type not set.';
                $errorMsgLink = 'Set MYP Final Achievement Levels Work Type';
            } elseif ((12 === $yearLevel || 2 === $reportSemester) && $attributes->isDp()) {
                $configName   = Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE;
                $errorMsg     = 'Cannot render DP Report components. Work type not set.';
                $errorMsgLink = 'Set DP Report Work Type';
            } else {
                // Component is not relevant for this report.
                continue;
            }

            try {
                $workTypeId = $this->checkWorkType($configName, $errorMsg, $errorMsgLink);
            } catch (UnexpectedValueException $e) {
                $reports[] = ['error' => $e->getMessage()];
                continue;
            }

            $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
            $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes, $reportSemester));
            $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
            foreach ($assessables as $assessable) {
                $unitImpl                                                                        = $assessable->getBaseContext();
                $baseAssessable                                                                  = $assessable->getBaseAssessable();
                $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
            }

            $ib = new InternationalBaccalaureateGraph(
                $cohortUnitImpls,
                $student,
                $attributes,
                $this->visibility,
                $this->getUser(),
                $workTypeId,
                $this->rubricMapper,
                $this->getAuthorizer(),
                $this->getRouter(),
                $folder,
                $roleTypeOverride
            );
            $ib->setSemesters($reportSemester);
            $context = $ib->toContext();
            if ($context['showComponent']) {
                $reports[] = $context;
            }
        }

        return $reports;
    }


    /**
     * Builds NESA Grade Graph report data.
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildNesaScoreGraphData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for:
        // * MYP S1
        // * MYP S2
        // * HSC-Y11-S2
        $yearLevel = $attributes->getCurriculumYearLevel();
        $semesters = $attributes->getCurriculumSemester() === 2 ? [2, 1] : [1];

        // Find relevant assessments and participation and insert into units
        $reports = [];
        foreach ($semesters as $reportSemester) {
            if (($reportSemester === 1 && $attributes->isMyp()) || (11 === $yearLevel && $reportSemester === 2 && $attributes->isHsc())) {
                $configName = Config::_TIGS_WORK_TYPE_NESA_SCORE;
                $errorMsgLink = 'Set NESA Grade Work Type';
            } elseif ($reportSemester === 2 && $attributes->isMyp()) {
                $configName = Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS;
                $errorMsgLink = 'Set MYP FAL Work Type';
            } else {
                // Component is not relevant for this report & semester.
                continue;
            }

            try {
            $workTypeId = $this->checkWorkType($configName, 'Cannot render NESA Grade graph. Work type not set.', $errorMsgLink);
            } catch (UnexpectedValueException $e) {
                $reports[] = ['error' => $e->getMessage()];
                continue;
            }

            $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
            $ids = $this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes, $reportSemester);
            $assessables = $this->assessmentMapperProxy->findAllByIds($ids);
            $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
            foreach ($assessables as $assessable) {
                $unitImpl = $assessable->getBaseContext();
                $baseAssessable = $assessable->getBaseAssessable();
                $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
            }

            $nesa = new NesaScoreGraph(
                $cohortUnitImpls,
                $student,
                $attributes,
                $this->visibility,
                $this->getUser(),
                $workTypeId,
                $folder,
                $roleTypeOverride
            );
            $nesa->setSemesters($reportSemester);
            $context = $nesa->toContext();
            if ($context['showComponent']) {
                $reports[] = $context;
            }
        }
        return $reports;
    }


    /**
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildHscTaskMarksData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for HSC students
        if (!$attributes->isHsc()) {
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_HSC_ASSESSMENT_TASK,
                "Cannot render 'HSC Task Marks' components. Work type not set.",
                "Set 'HSC Assessment Task' Work Type"
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        // Find relevant assessments and participation and insert into units
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes, 1, $attributes->getCurriculumSemester()));
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
        foreach ($assessables as $assessable) {
            $unitImpl                                                                        = $assessable->getBaseContext();
            $baseAssessable                                                                  = $assessable->getBaseAssessable();
            $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
        }

        $hscTaskMarks = new HscAssessmentTasks(
            $courseSpec,
            $student,
            $attributes,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $folder,
            $roleTypeOverride
        );
        $hscTaskMarks->setSemesters(1, $attributes->getCurriculumSemester());
        return ['data' => $hscTaskMarks->toContext()];
    }


    /**
     * Build data for the 'HSC Assessment Rank' component
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildHscAssessmentRankData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for HSC students in Semester 2
        if (!$attributes->isHsc() || 2 !== $attributes->getCurriculumSemester()) {
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_HSC_ASSESSMENT_RANK,
                "Cannot render 'HSC Assessment Rank' component. Work type not set.",
                "Set 'HSC Assessment Rank' Work Type"
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        // Find relevant assessments and participation and insert into units
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes, 1, $attributes->getCurriculumSemester()));
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
        foreach ($assessables as $assessable) {
            $unitImpl                                                                        = $assessable->getBaseContext();
            $baseAssessable                                                                  = $assessable->getBaseAssessable();
            $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
        }

        $hscRank = new HscAssessmentRank(
            $assessables,
            $student,
            $attributes,
            $this->visibility,
            $this->getUser(),
            $folder,
            $roleTypeOverride
        );
        $hscRank->setSemesters(1, $attributes->getCurriculumSemester());

        try {
            return $hscRank->toContext();
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Load the assessments into each unit implementation.
     *
     * @param GenericCollection|UnitImpl\Model[] $unitImpls
     * @return GenericCollection|UnitImpl\Model[]
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function loadAssessmentsIntoUnitImpls(GenericCollection $unitImpls): GenericCollection
    {
        foreach ($this->assessmentMapperProxy->findAllByUnitImplementationIdsForCourseOutline($unitImpls->keys()) as $assessable) {
            $unitImpls[$assessable->getBaseContext()->getId()]->getAssessables()[$assessable->getId()] = $assessable;
        }
        return $unitImpls;
    }


    /**
     * Efficiently fetch a set of assessment IDs where the assessments:
     * * Reside inside the specified Unit Implementations,
     * * Have the specified work type, and
     * * Are due within the specified range
     *
     * @param GenericCollection $unitImpls
     * @param int               $workTypeId
     * @param StudentAttributes $attributes
     * @param int|null $semesterFrom
     * @param int|null $semesterTo
     * @return array
     * @throws Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function findAssessmentIds(GenericCollection $unitImpls, int $workTypeId, StudentAttributes $attributes, ?int $semesterFrom = null, ?int $semesterTo = null): array
    {
        if (!\count($unitImpls)) {
            return [];
        }

        $semesters = $attributes->getCurriculumSemesters();
        if ($semesterFrom && \key_exists($semesterFrom, $semesters)) {
            $searchStart = $semesters[$semesterFrom]->getStart();
            if ($semesterTo && \key_exists($semesterTo, $semesters)) {
                $searchFinish = $semesters[$semesterTo]->getFinish();
            } else {
                // if semesterTo is not set, use the same semester as semesterFrom
                $searchFinish = $semesters[$semesterFrom]->getFinish();
            }
        } else {
            $searchStart = $attributes->getCurriculumSemesterStart();
            $searchFinish = $attributes->getCurriculumSemesterFinish();
        }

        $pb = (new Predicate\Builder(new \Alaress\Database\Quoter\Mysql\Quoter(), ''))
            ->isNull('a.deleted_at')
            ->in('ui.id', $unitImpls->getPropertyValues('id'))
            ->equals('a.work_type_id', $workTypeId)
            ->betweenDates('a.due_date', $searchStart, $searchFinish);

        $whereClause = \implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT a.id FROM `assessment` a
            JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
            JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
            WHERE {$whereClause}

            UNION

            SELECT a.id FROM `assessment` a
            JOIN project_assessment ap                     ON ap.assessment_id                  = a.id
            JOIN project p                                 ON ap.project_id                     = p.id 
            JOIN assessment_course_unit_implementation aui ON aui.assessment_id                 = p.assessment_id
            JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
            WHERE {$whereClause}
        ";

        return $this->database->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Checks that the specified workType config has been set and returns the workType's id
     *
     * If not set, throws an exception containing a HTML message fragment we can insert in the DOM to prompt
     * the user to configure the relevant config setting.
     *
     * @param string $configName
     * @param string $errorMsg
     * @param string $errorMsgLink
     * @return int
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws UnexpectedValueException
     */
    private function checkWorkType(string $configName, string $errorMsg, string $errorMsgLink): int
    {
        $workTypeId = $this->getConfigLoader()->get($configName);
        if (!\is_numeric($workTypeId)) {
            $routeConfig = $this->getRouteIfAuthorized('tigsSummativeAdmin.getAdmin');
            // If user is permitted, prompt them to fix this.
            if ($routeConfig) {
                $errorMsg .= " <a href =\"{$routeConfig}#{$configName}\">{$errorMsgLink}</a>";
            }
            throw new UnexpectedValueException($errorMsg);
        }
        return (int)$workTypeId;
    }
}
