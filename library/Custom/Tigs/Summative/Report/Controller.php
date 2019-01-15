<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate;
use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\Report\Components\AcademicAchievementMatrix;
use Custom\Tigs\Summative\Report\Components\AcademicAchievementSummary;
use Custom\Tigs\Summative\Report\Components\FinalAchievementLevels;
use Custom\Tigs\Summative\Report\Components\HscAssessmentTasks;
use Custom\Tigs\Summative\Report\Components\InternationalBaccalaureateGraph;
use Custom\Tigs\Summative\Report\Components\NesaScoreGraph;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Resource\Folder;
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
use Aura\Web\Request;

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

    /** @var Visibility */
    private $visibility;

    /** @var IConnector */
    private $database;


    /**
     * @param External_UserInterface $externalUser
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricMapper           $rubricMapper
     * @param Visibility             $visibility
     * @param IConnector             $database
     */
    public function __construct(
        External_UserInterface $externalUser,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricMapper $rubricMapper,
        Visibility $visibility,
        IConnector $database
    ) {
        $this->externalUser          = $externalUser;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->visibility            = $visibility;
        $this->database              = $database;
    }


    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
     * @todo Move to own controller
     */
    public function getParticipationReport(User $user): void
    {
        $data   = [
            'user'      => $user,
            'isEnabled' => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/participation.twig', $data, "Participation Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Displays a student's subject report for specified folder and current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}/folder/{folderId}?term=[1,2,3,4]
     * @level 18.1.0
     * @param User         $user
     * @param Folder\Model $folder
     * @throws ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \UnderflowException
     */
    public function getSubjectReport(User $user, Folder\Model $folder): void
    {
        $attributes = $this->deriveStudentAttributes($user, $this->getRequest()->query);

        $errors = [];

        $courseImplsWithUnits = $this->courseImplMapper->findByFolderId($folder->getId());

        // Check that multiple course specs have not been imported to this page
        $courseSpecs = $this->getImportedCourseSpecs($courseImplsWithUnits);
        if (1 !== \count($courseSpecs)) {
            // Don't bother loading model graph if we have 0 or multiple courses imported to this folder.
            // We can't render the page in this case because we rely on a single course-textbox being available.
            // It may be ok to import the same courseSpec multiple times, because each import may be a discrete subset of units.
            $error = \sprintf('There must be exactly 1 course imported to the class. Found %d.', $courseSpecs);
            $route = $this->getRouteIfAuthorized('courseImport.getManagement', ['folderId' => $folder->getId(), 'folder' => $folder]);
            if ($route) {
                $error .= \sprintf(' <a href="%s">Manage Course imports</a>', $route);
            }
            $errors[]  = $error;
            $unitSpecs = $aamData = $aasData = $hscTaskMarks = [];
            $falData   = $ibGraphData = $nesaGraphData = $courseSpec = null;
        } else {
            $this->loadParticipation($courseImplsWithUnits);
            $unitSpecs = $this->getUnitSpecs($courseImplsWithUnits);

            /** @var CourseSpec\Model $courseSpec */
            $courseSpec = $courseSpecs->first();

            // Build component data
            $ibGraphData   = $this->buildInternationalBaccalaureateGraphData($folder, $attributes, $courseSpec, $user);
            $nesaGraphData = $this->buildNesaScoreGraphData($folder, $attributes, $courseSpec, $user);
            $falData       = $this->buildFinalAchievementLevelsData($folder, $attributes, $unitSpecs, $user);
            $aamData       = $this->buildAcademicAchievementMatrixData($folder, $attributes, $unitSpecs, $user);
            $aasData       = $this->buildAcademicAchievementSummaryData($folder, $attributes, $unitSpecs, $user);
            $hscTaskMarks  = $this->buildHscTaskMarksData($folder, $attributes, $courseSpec, $user);
        }

        $data   = [
            'user'          => $user,
            'folder'        => $folder,
            'courseSpec'    => $courseSpec,
            'isEnabled'     => $this->getConfigLoader()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED),
            'attributes'    => $attributes,
            'errors'        => $errors,
            'unitSpecs'     => $unitSpecs,
            'falData'       => $falData,
            'aamData'       => $aamData,
            'aasData'       => $aasData,
            'ibGraphData'   => $ibGraphData,
            'nesaGraphData' => $nesaGraphData,
            'hscTaskMarks'  => $hscTaskMarks,
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Helper to derive relevant student attributes
     *
     * @param User           $user
     * @param Request\Values $query
     * @return StudentAttributes
     * @throws \InvalidArgumentException
     */
    private function deriveStudentAttributes(User $user, Request\Values $query): StudentAttributes
    {
        return new StudentAttributes(
            $user,
            $this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'],
            $query->get('now')
        );
    }


    /**
     * Returns all course implementations imported to the specified folder.
     *
     * Also loads units, assessments. and participation for all users
     *
     * @todo consider only loading participation of current student by default, load participation for all users for cohort components only.
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
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAcademicAchievementMatrixData(Folder\Model $folder, StudentAttributes $attributes, GenericCollection $unitSpecs, User $student): array
    {
        $configName = Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX;

        try {
            $workTypeId = $this->checkWorkType(
                $configName,
                'Cannot render Academic Achievement Matrix components. Work type not set.',
                'Set Academic Achievement Matrix Work Type'
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
            $this->getRouter()
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
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAcademicAchievementSummaryData(Folder\Model $folder, StudentAttributes $attributes, GenericCollection $unitSpecs, User $student): array
    {
        // Only relevant for MYP students
        if (!$attributes->isMyp()) {
            return [];
        }

        try {
            $workTypeId = $this->checkWorkType(
                Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY,
                'Cannot render Academic Achievement Summary components. Work type not set..',
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
            $this->getRouter()
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
     * @return array|null
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildFinalAchievementLevelsData(Folder\Model $folder, StudentAttributes $attributes, GenericCollection $unitSpecs, User $student): ?array
    {
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
            $this->getRouter()
        );

        return $fal->toContext();
    }


    /**
     * Builds IB Grade Graph report data.
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @return null|array
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildInternationalBaccalaureateGraphData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student
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
        } elseif ((12 === $yearLevel || 2 === $semester) && $attributes->isDp()) {
            $configName   = Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE;
            $errorMsg     = 'Cannot render DP Report components. Work type not set.';
            $errorMsgLink = 'Set DP Report Work Type';
        } else {
            // Component is not relevant for this report.
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType($configName, $errorMsg, $errorMsgLink);
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        // Find relevant assessments and participation and insert into units
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes));
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
            $folder
        );

        return $ib->toContext();
    }


    /**
     * Builds NESA Score Graph report data.
     *
     * @param Folder\Model      $folder
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @return array|null
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildNesaScoreGraphData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student
    ): ?array {
        // Only relevant for:
        // * MYP S1
        // * MYP S2
        // * HSC-Y11-S2
        $yearLevel = $attributes->getCurriculumYearLevel();
        $semester  = $attributes->getCurriculumSemester();
        if (($semester === 1 && $attributes->isMyp()) || (11 === $yearLevel && $semester === 2 && $attributes->isHsc())) {
            $configName   = Config::_TIGS_WORK_TYPE_NESA_SCORE;
            $errorMsgLink = 'Set NESA Score Work Type';
        } elseif ($semester === 2 && $attributes->isMyp()) {
            $configName   = Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS;
            $errorMsgLink = 'Set MYP FAL Work Type';
        } else {
            // Component is not relevant for this report.
            return null;
        }

        try {
            $workTypeId = $this->checkWorkType($configName, 'Cannot render NESA Score graph. Work type not set.', $errorMsgLink);
        } catch (UnexpectedValueException $e) {
            return ['error' => $e->getMessage()];
        }

        // Find relevant assessments and participation and insert into units
        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes));
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);
        foreach ($assessables as $assessable) {
            $unitImpl                                                                        = $assessable->getBaseContext();
            $baseAssessable                                                                  = $assessable->getBaseAssessable();
            $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
        }

        $nesa = new NesaScoreGraph(
            $cohortUnitImpls,
            $student,
            $attributes,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $folder
        );

        return $nesa->toContext();
    }


    /**
     * @param Folder\Model                       $folder
     * @param StudentAttributes                  $attributes
     * @param CourseSpec\Model                   $courseSpec
     * @param User                               $student
     * @return array|null
     * @throws Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnderflowException
     */
    private function buildHscTaskMarksData(
        Folder\Model $folder,
        StudentAttributes $attributes,
        CourseSpec\Model $courseSpec,
        User $student
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
        $assessables     = $this->assessmentMapperProxy->findAllByIds($this->findAssessmentIds($cohortUnitImpls, $workTypeId, $attributes));
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
            $folder
        );
        return ['data' => $hscTaskMarks->toContext()];
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
     * @return array
     * @throws Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    private function findAssessmentIds(GenericCollection $unitImpls, int $workTypeId, StudentAttributes $attributes): array
    {
        if (!\count($unitImpls)) {
            return [];
        }

        $pb = (new Predicate\Builder(new \Alaress\Database\Quoter\Mysql\Quoter(), ''))
            ->isNull('a.deleted_at')
            ->in('ui.id', $unitImpls->getPropertyValues('id'))
            ->equals('a.work_type_id', $workTypeId)
            ->betweenDates('a.due_date', $attributes->getReportStart(), $attributes->getReportFinish());

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
