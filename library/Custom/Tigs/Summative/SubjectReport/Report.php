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
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentMatrix;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentRank;
use Custom\Tigs\Summative\SubjectReport\Components\HscAssessmentTasks;
use Custom\Tigs\Summative\SubjectReport\Components\MiddleYearsAssessmentRank;
use Custom\Tigs\Summative\SubjectReport\Components\MiddleYearsAssessmentTasks;
use Custom\Tigs\Summative\SubjectReport\Components\NesaScoreGraphHSC;
use Custom\Tigs\Summative\SubjectReport\Components\NesaScoreGraphMiddleYears;
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
            $unitSpecsWithData = $aamData = $aasData = $hscTaskMarks = $hscTaskMatrix = $middleYearsTaskMarks = [];
            $nesaGraphData = $courseSpec = $hscRankData = $middleYearsRankData = $courseDescription = null;
        } else {
            $this->loadParticipation($courseImplsWithUnits);
            $unitSpecs = $this->getUnitSpecs($courseImplsWithUnits);

            /** @var CourseSpec\Model $courseSpec */
            $courseSpec = $courseSpecs->first();

            // Build component data
            $courseDescription = $this->buildCourseDescription($courseSpec);
            $nesaGraphData = $this->buildNesaScoreGraphData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $aamData       = $this->buildAcademicAchievementMatrixData($folder, $attributes, $unitSpecs, $user, $roleTypeOverride);
            $aasData       = $this->buildAcademicAchievementSummaryData($folder, $attributes, $unitSpecs, $user, $roleTypeOverride);
            $hscTaskMarks  = $this->buildHscTaskMarksData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $hscTaskMatrix = $this->buildHscTaskMatrixData($folder, $attributes, $unitSpecs, $courseSpec, $user, $roleTypeOverride);
            $hscRankData   = $this->buildHscAssessmentRankData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $middleYearsTaskMarks = $this->buildMiddleYearsTaskMarksData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);
            $middleYearsRankData = $this->buildMiddleYearsAssessmentRankData($folder, $attributes, $courseSpec, $user, $roleTypeOverride);

            // Filter out unit specs without any data
            $unitSpecsWithData = new Collection();
            foreach ($unitSpecs as $unitSpecId => $unitSpec) {
                // We do need to keep data for units that contain error messages, but which lack dates
                $hasData =
                    isset($aasData['data'][$unitSpecId]) ||
                    isset($aamData['data'][$unitSpecId]) ||
                    isset($hscTaskMarks['data'][$unitSpecId]) ||
                    isset($middleYearsTaskMarks['data'][$unitSpecId]);

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
            'aamData'                   => $aamData,
            'aasData'                   => $aasData,
            'nesaGraphData'             => $nesaGraphData,
            'hscTaskMarks'              => $hscTaskMarks,
            'hscTaskMatrix'             => $hscTaskMatrix,
            'hscRankData'               => $hscRankData,
            'middleYearsTaskMarks'      => $middleYearsTaskMarks,
            'middleYearsRankData'       => $middleYearsRankData
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
    private function getRouteIfAuthorized($name, array $params, User $user)
    {
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

        /* The Academic Achievement Matrix searches for assessments that have a
         * particular work type. These work types differ depending on whether you're
         * in the Middle Years Program or whether you're in HSC, producing a different
         * list accordingly.
         *
         * The assessments and their units' links depends on this being configured
         * and searched in this particular way. Misconfiguration results in units not
         * having their titles linked.
         */
        $identifier = null;

        if ($attributes->isMiddleYear()) {
            $identifier = Config::_TIGS_WORK_TYPE_7_10_ASSESSMENT_TASK;
        } elseif ($attributes->isHsc()) {
            $identifier = Config::_TIGS_WORK_TYPE_HSC_ASSESSMENT_TASK;
        }

        /* Error early when you can't detect which work type you're supposed to use
         */
        if (null === $identifier) {
            return ['error' => 'Cannot identify whether this student is in Middle Years program or HSC. Academic Achievement Summary not available.'];
        }

        /* Otherwise check that the work type existed somewhere in the config
         */
        try {
            $workTypeId = $this->checkWorkType(
                $identifier,
                'Cannot render Approaches to Learning components. Work type not set.',
                'Set Approaches to Learning Work Type'
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        /* Produce the Academic Achievement Matrix which is the rubric grid that correspond
         * their assessments which used a particular work type
         */
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

        /* Generically build an array of data via AbstractMatrix
         */
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

        /* The Academic Achievement Summary searches for assessments that have a
         * particular work type. These work types differ depending on whether you're
         * in the Middle Years Program or whether you're in HSC, producing a different
         * list accordingly.
         *
         * The assessments and their units' links depends on this being configured
         * and searched in this particular way. Misconfiguration results in units not
         * having their titles linked.
         */
        $identifier = null;

        if ($attributes->isMiddleYear()) {
            $identifier = Config::_TIGS_WORK_TYPE_7_10_ASSESSMENT_TASK;
        } elseif ($attributes->isHsc()) {
            $identifier = Config::_TIGS_WORK_TYPE_HSC_ASSESSMENT_TASK;
        }

        /* Error early when you can't detect which work type you're supposed to use
         */
        if (null === $identifier) {
            return ['error' => 'Cannot identify whether this student is in Middle Years program or HSC. Academic Achievement Summary not available.'];
        }

        /* Otherwise check that the work type existed somewhere in the config
         */
        try {
            $workTypeId = $this->checkWorkType(
                $identifier,
                'Cannot render Academic Achievement Summary components. Work type not set.',
                'Set 7-10 Assessment Task Work Type for Middle School, or HSC Assessment Task for other people'
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        /* Produce the Academic Achievement Summary which is the list of Units that contain
         * their assessments which used a particular work type
         */
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

        /* Generically build an array of data via AbstractMatrix
         */
        return $aas->toContext();
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
    ): ?array {
        // Only relevant for:
        // * MYP S1       (work-type = NESA)
        // * MYP S2       (work-type = FAL)
        // * HSC-Y11-S2   (work-type = NESA)
        $semester  = $attributes->getCurriculumSemester();

        // Which work types should we fetch?
        $configNames = [Config::_TIGS_WORK_TYPE_NESA_SCORE => 'Set MYP FAL Work Type'];
        $configNames[Config::_TIGS_WORK_TYPE_NESA_REPORT_GRADE_AND_REPORT_MARK] = 'Set NESA Grade Work Type';

        $workTypeIds = [];

        foreach ($configNames as $configName => $errorMsgLink) {
            try {
                $workTypeIds[$configName] = $this->checkWorkType($configName, 'Cannot render NESA Grade graph. Work type not set.', $errorMsgLink);
            } catch (UnexpectedValueException $e) {
                return $this->showErrorsToViewer($roleTypeOverride) ? ['error' => $e->getMessage()] : null;
            }
        }

        /* Find all the unit implementations that were given birth by $courseSpec
         * This gives us all the guys in the cohort whom we must compare against
         */
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);

        // Find relevant assessments and participation and insert into units
        $reports = [];

        /* Second semester get the NESA grade from a special assessment, a different one
         * than for semester 1
         *
         * Truth table time
         * Note that Prod (PRD) and Staging (STG) can have different ids
         *
         * HSC Students, Year 11, Semester 1: worktype is not relevant (no NESA data for this)
         * HSC Students, Year 11, Semester 2: worktype is "NESA Report Grade" (STG 20)
         *
         * HSC Students, Year 12, Semester 1: worktype is not relevant (no NESA data for this)
         * HSC Students, Year 12, Semester 2: worktype is not relevant (no NESA data for this)
         *
         * Middle Years Students, Semester 1: worktype is "NESA Report Grade" (STG 20)
         * Middle Years Students, Semester 2: worktype is "NESA Report Grade & Report Mark" (STG 22)
         *
         * NESA Graphs render in special ways depending on whether the student
         * is an HSC or Middle Years student
         */
        if ($attributes->isHsc()) {
            if ($semester === 2) {
                if ($attributes->getYearLevel() === 11) {
                    $this->loadAssessmentsAndParticipation($courseSpec, $workTypeIds, $attributes, 2, 2);

                    $nesa = new NesaScoreGraphHSC(
                        $cohortUnitImpls,
                        $student,
                        $attributes,
                        $this->visibility,
                        $this->viewer,
                        $workTypeIds[Config::_TIGS_WORK_TYPE_NESA_SCORE],
                        $folder,
                        $roleTypeOverride
                    );

                    $context = $nesa->toContext($semester);

                    if ($context !== null) {
                        $reports['data'][$semester] = $context;
                    }
                }
            } else {
                // noop - no NESA graph for HSC students except Year 11 Semester 2 guys
                return [];
            }
        } elseif ($attributes->isMiddleYear()) {
            $this->loadAssessmentsAndParticipation($courseSpec, $workTypeIds, $attributes, 1, 2);

            /* Middle Years NESA data spans both semester 1 and 2.
             * They both appear next to each other, so you need to collect data
             * for at least semester 1 and if you're in semester 2, that as well
             */
            foreach ([1, $semester] as $reportSemester) {
                /* Semester 1 NESA data comes from a different worktype than the
                 * Semester 2 NESA data...have to treat them specially
                 */
                if ($reportSemester === 1) {
                    $workType = $workTypeIds[Config::_TIGS_WORK_TYPE_NESA_SCORE];
                } else {
                    $workType = $workTypeIds[Config::_TIGS_WORK_TYPE_NESA_REPORT_GRADE_AND_REPORT_MARK];
                }

                /* This class does an internal check on $reportSemester and changes behaviour accordingly
                 * The NESA grade and its data for semester 1 are obtained differently than semester 2
                 */
                $nesa = new NesaScoreGraphMiddleYears(
                    $cohortUnitImpls,
                    $student,
                    $attributes,
                    $this->visibility,
                    $this->viewer,
                    $workType,
                    $folder,
                    $reportSemester,
                    $this->rubricMapper,
                    $roleTypeOverride
                );

                $context = $nesa->toContext($reportSemester);

                if ($context !== null) {
                    $reports['data'][$reportSemester] = $context;
                }
            }
        } else {
            /* Can't tell if this student is HSC or Middle Years
             * Return empty
             */
            return [];
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
     * This is a little table of information for each Middle Years student
     * Their task marks table appears next to the comment area, if it exists,
     * for each assessment
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
    private function buildMiddleYearsTaskMarksData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {

        /* Only relevant to Middle Year students
         */
        if (!$attributes->isMiddleYear()) {
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_7_10_ASSESSMENT_TASK,
                "Cannot render 'Middle Years Task Marks' components. Work type not set.",
                "Set '7-10 Assessment Task' Work Type"
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, 1, $attributes->getCurriculumSemester());

        $middleYearsTaskMarks = new MiddleYearsAssessmentTasks(
            $courseSpec,
            $student,
            $attributes,
            $this->visibility,
            $this->viewer,
            $workTypeId,
            $folder,
            $roleTypeOverride
        );

        return ['data' => $middleYearsTaskMarks->toContext()];
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
     * Build data for the 'Middle Years Assessment Rank' component
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
    private function buildMiddleYearsAssessmentRankData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student,
        ?string $roleTypeOverride
    ): ?array {

        /* Only for Middle Years students
         */
        if (!$attributes->isMiddleYear()) {
            return null;
        }

        /* We are currently in this semester
         */
        $currentSemester = $attributes->getCurriculumSemester();

        /* This services worktypes found in both semesters.
         * The Summative Grade for semester 1 uses the worktype "NESA Report Grade"
         * The Summative Grade for semester 2 uses the worktype "NESA Report Grade And Report Mark"
         *
         * These names would typically be derived from the WorkType instance itself but I don't
         * have one of these here conveniently atm. I'd have to modify two places to inject it
         * into every instance of Report currently in use which seems a bit extreme for debugging
         */
        if ($currentSemester === 1) {
            $workTypeName = 'NESA Report Grade';
            $identifier = Config::_TIGS_WORK_TYPE_NESA_SCORE;
        } elseif ($currentSemester === 2) {
            $workTypeName = 'NESA Report Grade and Report Mark';
            $identifier = Config::_TIGS_WORK_TYPE_NESA_REPORT_GRADE_AND_REPORT_MARK;
        } else {
            return ['error' => 'Unknown semester detected in Middle Years Assessment Rank data'];
        }

        try {
            $workTypeId = $this->checkWorkType(
                $identifier,
                "Cannot render 'Middle Years Assessment Rank' component. Work type not set.",
                "Set '{$workTypeName}' Work Type"
            );
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        /* Only get assessments for the current semester
         */
        $assessables = $this->loadAssessmentsAndParticipation($courseSpec, [$workTypeId], $attributes, $currentSemester, $currentSemester);

        $middleYearsRank = new MiddleYearsAssessmentRank(
            $assessables,
            $student,
            $attributes,
            $this->visibility,
            $this->viewer,
            $folder,
            $workTypeName,
            $roleTypeOverride
        );

        try {
            return $middleYearsRank->toContext();
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }
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
     * @param int               $semesterFrom
     * @param int               $semesterTo
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
