<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\Components\AcademicAchievementMatrix;
use Custom\Tigs\Summative\SubjectReport\Components\AcademicAchievementSummary;
use Custom\Tigs\Summative\SubjectReport\Components\FinalAchievementLevels;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentMatrix;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentRank;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentTasks;
use Custom\Tigs\Summative\SubjectReport\Components\InternationalBaccalaureateGraph;
use Custom\Tigs\Summative\SubjectReport\Components\NesaScoreGraph;
use DomainException;
use External_ClassInterface;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Assessment\Rubric\DataModel as RubricDataModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\UserMapper;
use UnderflowException;
use function strnatcasecmp;
use UnexpectedValueException;
use Schoolbox\Filter\Filters;
use PDO;
use Schoolbox\Resource\Textbox;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;

class Report
{
    /** @var IConfigLoader */
    private $config;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricDataModel\DataSource */
    private $rubricMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Textbox\Mapper */
    private $textBoxMapper;

    /** @var Visibility */
    private $visibility;

    /** @var IConnector */
    private $database;

    private $authorizer;

    private $router;

    /** @var User */
    private $viewer;


    /**
     * @param IConfigLoader          $config
     * @param External_UserInterface $externalUser
     * @param External_ClassInterface $externalClass
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricDataModel\DataSource $rubricMapper
     * @param UserMapper             $userMapper
     * @param Assessment\Mapper      $assessmentMapper
     * @param Textbox\Mapper         $textboxMapper
     * @param Visibility             $visibility
     * @param IConnector             $database
     * @param Authorizer             $authorizer
     * @param Router                 $router
     * @param User                   $viewer
     */
    public function __construct(
        IConfigLoader $config,
        External_UserInterface $externalUser,
        External_ClassInterface $externalClass,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricDataModel\DataSource $rubricMapper,
        UserMapper $userMapper,
        Assessment\Mapper $assessmentMapper,
        Textbox\Mapper $textboxMapper,
        Visibility $visibility,
        IConnector $database,
        Authorizer $authorizer,
        Router $router,
        User $viewer
    ) {
        $this->config                = $config;
        $this->externalUser          = $externalUser;
        $this->externalClass         = $externalClass;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->userMapper            = $userMapper;
        $this->assessmentMapper      = $assessmentMapper;
        $this->textBoxMapper         = $textboxMapper;
        $this->visibility            = $visibility;
        $this->database              = $database;
        $this->authorizer            = $authorizer;
        $this->router                = $router;
        $this->viewer                = $viewer;
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/subject/user/{userId}/folder/{folderId}{/reportDate}
     *            Where {reportDate} is anything recognised by strtotime()
     * @level 18.1.0
     *
     * @param User              $user
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param string|null       $roleTypeOverride
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws MapperException
     * @throws UnderflowException
     * @throws Database\Exception
     */
    public function getReport(User $user, Folder\Model $folder, StudentAttributes $attributes, ?string $roleTypeOverride): array
    {
        $errors = [];

        $courseImplsWithUnits = $this->courseImplMapper->findByFolderId($folder->getId());

        // Check that multiple course specs have not been imported to this page
        $courseSpecs = $this->getImportedCourseSpecs($courseImplsWithUnits);
        if (1 !== \count($courseSpecs)) {
            // Don't bother loading model graph if we have 0 or multiple courses imported to this folder.
            // We can't render the page in this case because we rely on a single course-textbox being available.
            // It may be ok to import the same courseSpec multiple times, because each import may be a discrete subset of assessments.
            $error = \sprintf('There must be exactly 1 course imported to the class. Found %d.', $courseSpecs->count());
            $route = $this->getRouteIfAuthorized('courseImport.getManagement', ['folderId' => $folder->getId(), 'folder' => $folder], $this->viewer);
            if ($route) {
                /** @noinspection HtmlUnknownTarget */
                $error .= \sprintf(' <a href="%s">Manage Course imports</a>', $route);
            }
            $errors[]  = $error;
            $unitSpecsWithData = $aamData = $aasData = $hscTaskMarks = $hscTaskMatrix = [];
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
            $hscTaskMatrix = $this->buildHscTaskMatrixData($folder, $attributes,$unitSpecs, $courseSpec, $user, $roleTypeOverride);
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
                    return $dueDateB ? $dueDateB->compare($dueDateA) : +1;
                }
            );
        }

        $teacherFetcher = new TeacherFetcher($this->externalClass, $this->userMapper, $this->assessmentMapper);

        return [
            'folder'                    => $folder,
            'teachers'                  => $teacherFetcher->getTeachers($folder, $attributes),
            'courseSpec'                => $courseSpec,
            'showNoLongerEnrolledAlert' => !$attributes->isHistorical() && !$this->isCurrentEnrollment($user, $folder),
            'attributes'                => $attributes,
            'errors'                    => $errors,
            'unitSpecs'                 => $unitSpecsWithData,
            'courseDescription'         => $courseDescription,
            'falData'                   => $falData,
            'aamData'                   => $aamData,
            'aasData'                   => $aasData,
            'ibGraphData'               => $ibGraphData,
            'nesaGraphData'             => $nesaGraphData,
            'hscTaskMarks'              => $hscTaskMarks,
            'hscTaskMatrix'             => $hscTaskMatrix,
            'hscRankData'               => $hscRankData,
        ];
    }

    /**
     * Attempts to authorize a route for a user. Returns the URL for that route
     * if authorized, and false if not.
     *
     * @param  string $name   The name of the route to authorize and generate.
     * @param  array  $params Any params to pass to the route generator.
     * @param  User   $user   The user to authorize the route for. Defaults to
     *                        currently authenticated user.
     * @return string|bool    The URL for the route if authorized, false if not.
     * @throws RouteNotFound
     */
    private function getRouteIfAuthorized($name, array $params, User $user) {
        if ($this->authorizer->isPathAuthorizedForUser($name, $params, $user)) {
            return $this->router->generate($name, $params);
        }
        return false;
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
     * Loads assessments and participation for all users into the specified course implementations
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImplsWithUnits
     * @throws InvalidColumnException
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
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

        return $unitSpecs->sortByCallback(static function (UnitSpec\Model $left, UnitSpec\Model $right) {
            return strnatcasecmp($left->getTitle(), $right->getTitle());
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
     * @throws RouteNotFound
     * @throws MapperException
     */
    private function buildAcademicAchievementMatrixData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        ?string $roleTypeOverride
    ): array {
        // HSC Yr 11 students uses rubric from Hsc Task Mark
        if ($attributes->getYearLevel() === 11 && $attributes->isHsc()) {
            return [];
        }

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
            $this->viewer,
            $workTypeId,
            $this->rubricMapper,
            $this->authorizer,
            $this->router,
            $roleTypeOverride
        );

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
     * @throws RouteNotFound
     * @throws MapperException
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
            $this->viewer,
            $workTypeId,
            $this->rubricMapper,
            $this->authorizer,
            $this->router,
            $roleTypeOverride
        );
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
     * @throws RouteNotFound
     * @throws MapperException
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
            $this->viewer,
            $workTypeId,
            $this->rubricMapper,
            $this->authorizer,
            $this->router,
            $roleTypeOverride
        );

        return $fal->toContext();
    }


    /**
     * Returns the body of a textbox with a specific heading on the CourseSpec's homepage
     *
     * @param CourseSpec\Model $courseSpec
     * @return string|null
     * @throws InvalidColumnException
     */
    private function buildCourseDescription(CourseSpec\Model $courseSpec): ?string
    {
        $textboxHeading = $this->config->get(Config::_TEXTBOX_HEADING);
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
     * Returns IB Grade Graph report data for Sem1 and optionally Sem2.
     *
     * Returns null if not relevant to student.
     *
     * Return structure:
     * @internal string     return['error']                       Optional. Info about why this component could not be built
     * @internal array      return['data']
     * @internal null|array return['data'][idx]                   Where idx is [1,2] and represents data for a semester. Null if don't show component
     * @internal string     return['data'][idx]['header']
     * @internal string     return['data'][idx]['data']           Mark data
     * @internal string     return['data'][idx]['studentMark']    Student's mark
     * @internal string     return['data'][idx]['studentComment'] Student's comment
     * @internal string     return['data'][idx]['useComment']     Whether we should just render the student comment instead of the graph
     * @internal int        return['data'][idx]['semester']       The semester the data belongs to
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return null|array
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws Database\Exception
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
        if (2 === $semester && $attributes->isMyp()) {
            $configName   = Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS;
            $errorMsg     = 'Cannot render MYP Report components. Work type not set.';
            $errorMsgLink = 'Set MYP Final Achievement Levels Work Type';
            // Only ever interested in Sem2 for MYP FAL
            $semesterFrom = 2;
            $semesterTo   = 2;
        } elseif ((12 === $yearLevel || 2 === $semester) && $attributes->isDp()) {
            $configName   = Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE;
            $errorMsg     = 'Cannot render DP Report components. Work type not set.';
            $errorMsgLink = 'Set DP Report Work Type';
            if(11 === $yearLevel){
                // Only interested in Sem2 for Yr 11 DP
                $semesterFrom = 2;
                $semesterTo   = 2;
            } else {
                // Always show Sem1, show Sem2 if report is for Sem2
                $semesterFrom = 1;
                $semesterTo   = $semester;
            }
        } else {
            // Component is not relevant for this report.
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType($configName, $errorMsg, $errorMsgLink);
        } catch (UnexpectedValueException $e) {
            return $this->showErrorsToViewer($roleTypeOverride) ? ['error' => $e->getMessage()] : null;
        }

        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, $semesterFrom, $semesterTo);

        $semesters = [];
        foreach([2,1] as $candidate){
            if($candidate === $semesterFrom || $candidate === $semesterTo){
                $semesters[] = $candidate;
            }
        }

        $reports = [];
        foreach($semesters as $semester){
            $ib = new InternationalBaccalaureateGraph(
                $cohortUnitImpls,
                $student,
                $attributes,
                $this->visibility,
                $this->viewer,
                $workTypeId,
                $this->rubricMapper,
                $this->authorizer,
                $this->router,
                $folder,
                $roleTypeOverride
            );
            $context = $ib->toContext($semester);
            if ($context) {
                $reports['data'][$semester] = $context;
            }
        }

        return $reports;
    }


    /**
     * Returns whether errors should be displayed to report viewer
     *
     * @param string|null $roleTypeOverride
     * @return bool
     */
    private function showErrorsToViewer(?string $roleTypeOverride): bool
    {
        $effectiveRole = $roleTypeOverride ?? $this->viewer->getRole()->getType();

        return $effectiveRole === Role::STAFF;
    }


    /**
     * Builds NESA Grade Graph report data.
     *
     * Return structure:
     * @internal string      return['error']                       Optional. Info about why these components could not be built
     * @internal array       return['data']
     * @internal null|array  return['data'][idx]                   Where idx is [1,2] and represents data for a semester. Null if don't show component
     * @internal null|string return['data'][idx]['error']          Optional. Info why this component cannot be built for specific semester
     * @internal array       return['data'][idx]['cohortMarks']
     * @internal string      return['data'][idx]['studentMark']
     * @internal int         return['data'][idx]['semester']
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws Database\Exception
     */
    private function buildNesaScoreGraphData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array
    {
        // Only relevant for:
        // * MYP S1       (work-type = NESA)
        // * MYP S2       (work-type = FAL)
        // * HSC-Y11-S2   (work-type = NESA)
        $yearLevel = $attributes->getCurriculumYearLevel();
        $semester  = $attributes->getCurriculumSemester();
        if (!($attributes->isMyp() || (11 === $yearLevel && $semester === 2 && $attributes->isHsc()))) {
            return null;
        }

        // Which work types should we fetch?
        $configNames = [Config::_TIGS_WORK_TYPE_NESA_SCORE => 'Set MYP FAL Work Type'];
        if ($semester === 2 && $attributes->isMyp()) {
            $configNames[Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS] = 'Set NESA Grade Work Type';
        }
        $workTypeIds = [];
        foreach ($configNames as $configName => $errorMsgLink) {
            try {
                $workTypeIds[$configName] = $this->checkWorkType($configName, 'Cannot render NESA Grade graph. Work type not set.', $errorMsgLink);
            } catch (UnexpectedValueException $e) {
                return $this->showErrorsToViewer($roleTypeOverride) ? ['error' => $e->getMessage()] : null;
            }
        }

        // Which semesters' data should we fetch?
        $semesterFrom = $attributes->isHsc() ? 2 : 1;
        $semesterTo   = $semester;

        // Load assessments and participations
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $this->loadAssessmentsAndParticipation($courseSpec, $workTypeIds, $attributes, $semesterFrom, $semesterTo);

        $semesters = [];
        foreach ([2, 1] as $candidate) {
            if ($candidate === $semesterFrom || $candidate === $semesterTo) {
                $semesters[] = $candidate;
            }
        }

        // Find relevant assessments and participation and insert into units
        $reports = [];
        foreach ($semesters as $reportSemester) {

            $workTypeId = $reportSemester === 2 && $attributes->isMyp()
                ? $workTypeIds[Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS]
                : $workTypeIds[Config::_TIGS_WORK_TYPE_NESA_SCORE];

            $nesa    = new NesaScoreGraph(
                $cohortUnitImpls,
                $student,
                $attributes,
                $this->visibility,
                $this->viewer,
                $workTypeId,
                $folder,
                $roleTypeOverride
            );
            $context = $nesa->toContext($reportSemester);
            if ($context !== null) {
                $reports['data'][$reportSemester] = $context;
            }
        }
        return $reports;
    }


    /**
     * @param Folder\Model                       $folder
     * @param StudentAttributes                  $attributes
     * @param GenericCollection|UnitSpec\Model[] $unitSpecs
     * @param CourseSpec\Model                   $courseSpec
     * @param User                               $student
     * @param string|null                        $roleTypeOverride
     * @return array|null
     * @throws RouteNotFound
     * @throws MapperException
     */
    private function buildHscTaskMatrixData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {
        // Only relevant for HSC Yr 11 students
        if ($attributes->getYearLevel() !== 11 || !$attributes->isHsc()) {
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

        $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, 1, $attributes->getCurriculumSemester());

        $hscTaskMarks = new HscAssessmentMatrix(
            $attributes,
            $unitSpecs,
            $student,
            $folder,
            $this->visibility,
            $this->viewer,
            $workTypeId,
            $this->rubricMapper,
            $this->authorizer,
            $this->router,
            $roleTypeOverride
        );
        return $hscTaskMarks->toContext();
    }


    /**
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param string|null       $roleTypeOverride
     * @return array|null
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws Database\Exception
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

        $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, 1, $attributes->getCurriculumSemester());

        $hscTaskMarks = new HscAssessmentTasks(
            $courseSpec,
            $student,
            $attributes,
            $this->visibility,
            $this->viewer,
            $workTypeId,
            $folder,
            $roleTypeOverride
        );
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
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws Database\Exception
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

        $assessables = $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, 2, 2);

        $hscRank = new HscAssessmentRank(
            $assessables,
            $student,
            $attributes,
            $this->visibility,
            $this->viewer,
            $folder,
            $roleTypeOverride
        );

        try {
            return $hscRank->toContext();
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }
    }


    /**
     * Loads assessables and participations from database into model graph
     *
     * @param CourseSpec\Model  $courseSpec
     * @param array             $workTypeIds
     * @param StudentAttributes $attributes
     * @param int               $semesterFrom
     * @param int               $semesterTo
     * @return GenericCollection<IAssessable>|IAssessable[]
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     */
    private function loadAssessmentsAndParticipation(
        CourseSpec\Model $courseSpec,
        array $workTypeIds,
        StudentAttributes $attributes,
        int $semesterFrom,
        int $semesterTo
    ): GenericCollection {
        // Find relevant assessments and participation and insert into units
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeIds, $attributes, $semesterFrom, $semesterTo));
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
        foreach ($assessables as $assessable) {
            $unitImpl                                                                        = $assessable->getBaseContext();
            $baseAssessable                                                                  = $assessable->getBaseAssessable();
            $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
        }
        return $assessables;
    }


    /**
     * Load the assessments into each unit implementation.
     *
     * @param GenericCollection|UnitImpl\Model[] $unitImpls
     * @return GenericCollection|UnitImpl\Model[]
     * @throws UnexpectedValueException
     * @throws InvalidColumnException
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
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
     * * Have the specified work types, and
     * * Are due within the specified range
     *
     * @param GenericCollection $unitImpls
     * @param int[]             $workTypeIds
     * @param StudentAttributes $attributes
     * @param int|null          $semesterFrom
     * @param int|null          $semesterTo
     * @return array
     * @throws InvalidColumnException
     * @throws \Alaress\Database\Exception
     * @throws UnderflowException
     */
    private function findAssessmentIds(GenericCollection $unitImpls, array $workTypeIds, StudentAttributes $attributes, int $semesterFrom, int $semesterTo): array
    {
        if (!\count($unitImpls)) {
            return [];
        }
        $semesters    = $attributes->getCurriculumSemesters();
        $searchStart  = $semesters[$semesterFrom]->getStart();
        $searchFinish = $semesters[$semesterTo]->getFinish();

        $pb = (new Predicate\Builder(new \Alaress\Database\Quoter\Mysql\Quoter(), ''))
            ->isNull('a.deleted_at')
            ->in('ui.id', $unitImpls->getPropertyValues('id'))
            ->in('a.work_type_id', $workTypeIds)
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
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     */
    private function checkWorkType(string $configName, string $errorMsg, string $errorMsgLink): int
    {
        $workTypeId = $this->config->get($configName);
        if (!\is_numeric($workTypeId)) {
            $routeConfig = $this->getRouteIfAuthorized('tigsSummativeAdmin.getAdmin', [], $this->viewer);
            // If user is permitted, prompt them to fix this.
            if ($routeConfig) {
                $errorMsg .= " <a href =\"{$routeConfig}#{$configName}\">{$errorMsgLink}</a>";
            }
            throw new UnexpectedValueException($errorMsg);
        }
        return (int)$workTypeId;
    }
}
