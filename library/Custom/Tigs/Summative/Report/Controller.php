<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report;

use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\Report\Components\AcademicAchievementMatrix;
use Custom\Tigs\Summative\Report\Components\AcademicAchievementSummary;
use Custom\Tigs\Summative\Report\Components\FinalAchievementLevels;
use Custom\Tigs\Summative\Report\Components\InternationalBaccalaureateGraph;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use UnexpectedValueException;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Term\Mapper */
    private $termMapper;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricMapper */
    private $rubricMapper;

    /** @var Visibility */
    private $visibility;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;


    /**
     * @param External_UserInterface             $externalUser
     * @param Term\Mapper                        $termMapper
     * @param CourseImpl\Mapper                  $courseImplMapper
     * @param Assessment\MapperProxy             $assessmentMapperProxy
     * @param RubricMapper                       $rubricMapper
     * @param Access                             $access
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     */
    public function __construct(
        External_UserInterface $externalUser,
        Term\Mapper $termMapper,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricMapper $rubricMapper,
        Access $access,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder
    ) {
        $this->externalUser          = $externalUser;
        $this->termMapper            = $termMapper;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->visibility            = new Visibility($access);
        $this->assessmentIdFinder    = $assessmentIdFinder;
    }


    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
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
     */
    public function getSubjectReport(User $user, Folder\Model $folder): void
    {
        $attributes = $this->deriveStudentAttributes($user);

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
            $unitImpls = $aamData = $aasData = [];
            $falData   = $ibGraphData = null;
        } else {
            $this->loadParticipation($courseImplsWithUnits);
            $unitImpls = $this->getUnitImpls($courseImplsWithUnits);

            /** @var CourseSpec\Model $courseSpec */
            $courseSpec = $courseSpecs->first();

            // Build component data
            $ibGraphData = $this->buildInternationalBaccalaureateGraphData($attributes, $courseSpec, $user);
            $falData     = $this->buildFinalAchievementLevelsData($attributes, $unitImpls, $user);
            $aamData     = $this->buildAcademicAchievementMatrixData($unitImpls, $user);
            $aasData     = $this->buildAcademicAchievementSummaryData($attributes, $unitImpls, $user);
        }


        $data   = [
            'user'                => $user,
            'folder'              => $folder,
            'isEnabled'           => $this->getConfigLoader()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED),
            'attributes'          => $attributes,
            'errors'              => $errors,
            'unitImpls'           => $unitImpls,
            'falData'             => $falData,
            'aamData'             => $aamData,
            'aasData'             => $aasData,
            'ibGraphData'         => $ibGraphData,
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Helper to derive relevant student attributes
     *
     * @param User $user
     * @return StudentAttributes
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function deriveStudentAttributes(User $user): StudentAttributes
    {
        // Allow GET param to override real ibFlag
        $isIb = $this->getRequest()->query->get('ibFlag');
        if(null === $isIb){
            $isIb = (bool)$this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'];
        } else {
            $isIb = (bool)$isIb;
        }


        $year  = (int)$user->getYear();

        // Hardcoded term dates for now
        $firstDayTerm1Relative = '1 January';
        $firstDayTerm2Relative = '1 April';
        $firstDayTerm3Relative = '1 July';
        $firstDayTerm4Relative = '1 October';

        $term = $this->getRequest()->query->get('term');
        if (!$term || !preg_match('/^[1234]$/', $term)) {
            // Current Term + Semester
            $now           = \Alaress_Date_Format::now();
            $firstDayTerm1 = new \Alaress_Date_Format(\strtotime($firstDayTerm1Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm2 = new \Alaress_Date_Format(\strtotime($firstDayTerm2Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm3 = new \Alaress_Date_Format(\strtotime($firstDayTerm3Relative), \Alaress_Date_Format::TIMESTAMP);
            $firstDayTerm4 = new \Alaress_Date_Format(\strtotime($firstDayTerm4Relative), \Alaress_Date_Format::TIMESTAMP);
            if ($now->gte($firstDayTerm1) && $now->lt($firstDayTerm2)) {
                $term = 1;
            } elseif ($now->lt($firstDayTerm3)) {
                $term = 2;
            } elseif ($now->lt($firstDayTerm4)) {
                $term = 3;
            } else {
                $term = 4;
            }
            $semTitle = $this->termMapper->findForDate($now)->getTitle();

            // Vagrant + TIGS current share the same term title scheme, eg 'Semester 2, 2018'
            if (!\preg_match('/Semester (\d)/', $semTitle, $matches)) {
                throw new \UnexpectedValueException("Unable to recognise a semester");
            }
            $sbSem = (int)$matches[1];
        } else {
            $term = (int)$term;
            // Simulated Term + Semester
            switch ($term) {
                case 1:
                case 2:
                    $sbSem = 1;
                    break;
                case 3:
                case 4:
                    $sbSem = 2;
                    break;
                default:
                    throw new \UnexpectedValueException("Unable to recognise a term");
            }
        }

        // Apply SB to Curriculum mapping
        if (4 === $term && $year === 11) {
            // Year 11, Term 4 => Sem1 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 1;
        } elseif (2 === $term && $year === 12) {
            // Year 12, Term 2 => Sem2 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 2;
        } else {
            // Normal Case
            $curriculumYearLevel = $year;
            $curriculumSemester  = $sbSem;
        }

        return new StudentAttributes($isIb, $sbSem, $term, $curriculumYearLevel, $curriculumSemester);
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
            $spec = $courseImpl->getSpecification();
            $courseSpecs[$spec->getId()] = $spec;
        }
        return $courseSpecs;
    }


    /**
     * Gets a list of all Units to render, sorted by unit title
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImports
     * @return GenericCollection|UnitImpl\Model[]
     * @throws UnexpectedValueException
     */
    private function getUnitImpls(GenericCollection $courseImports): GenericCollection
    {
        $unitImpls = new GenericCollection(UnitImpl\Model::class);

        foreach ($courseImports as $courseImport) {
            foreach ($courseImport->getUnits() as $unitImpl) {
                $unitImpls[$unitImpl->getId()] = $unitImpl;
            }
        }

        return $unitImpls->sortByCallback(function (UnitImpl\Model $left, UnitImpl\Model $right) {
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
            foreach($courseImport->getUnits() as $unitImpl){
                $cohortUnitImpls[$unitImpl->getId()] = $unitImpl;
            }
        }
        return $cohortUnitImpls;
    }


    /**
     * Builds data for rendering the AAM components.
     *
     * @param GenericCollection|UnitImpl\Model[] $unitImpls
     * @param User                               $student
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function buildAcademicAchievementMatrixData(GenericCollection $unitImpls, User $student): array
    {
        $aamWorkTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX);
        $aamWorkTypeId = \is_numeric($aamWorkTypeId) ? (int)$aamWorkTypeId : null;

        // Not set? Then we can't identify AAM assessments.
        if (null === $aamWorkTypeId) {
            $errorMsg = 'Cannot render Academic Achievement Matrix components. Work type not set.';
            // If user is superuser, prompt them to fix this.
            if ($this->getUser()->isSuperuser()) {
                $routeConfig = $this->getRouter()->generate('tigsSummativeAdmin.getAdmin');
                $hash        = Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX;
                $errorMsg    .= " <a href =\"{$routeConfig}#{$hash}\">Set Academic Achievement Matrix Work Type</a>";
            }
            return ['error' => $errorMsg];
        }

        $aam = new AcademicAchievementMatrix(
            $unitImpls,
            $student,
            $this->visibility,
            $this->getUser(),
            $aamWorkTypeId,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter()
        );

        return $aam->toContext();
    }


    /**
     * Builds data for rendering the AAS components.
     *
     * @param StudentAttributes                  $attributes
     * @param GenericCollection|UnitImpl\Model[] $unitImpls
     * @param User                               $student
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAcademicAchievementSummaryData(StudentAttributes $attributes, GenericCollection $unitImpls, User $student): array
    {
        // Only relevant for MYP students
        if(!$attributes->isMyp()){
            return [];
        }

        $aasWorkTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY);
        $aasWorkTypeId = \is_numeric($aasWorkTypeId) ? (int)$aasWorkTypeId : null;

        // Not set? Then we can't identify AAS assessments.
        if (null === $aasWorkTypeId) {
            $errorMsg = 'Cannot render Academic Achievement Summary components. Work type not set.';
            $routeConfig = $this->getRouteIfAuthorized('tigsSummativeAdmin.getAdmin');
            // If user is permitted, prompt them to fix this.
            if ($routeConfig) {
                $hash        = Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY;
                $errorMsg    .= " <a href =\"{$routeConfig}#{$hash}\">Set Academic Achievement Summary Work Type</a>";
            }
            return ['error' => $errorMsg];
        }

        $aas = new AcademicAchievementSummary(
            $unitImpls,
            $student,
            $this->visibility,
            $this->getUser(),
            $aasWorkTypeId,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter()
        );

        return $aas->toContext();
    }


    /**
     * Build data for the MYP-FAL component.
     *
     * @param StudentAttributes $attributes
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @return array|null
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildFinalAchievementLevelsData(StudentAttributes $attributes, GenericCollection $unitImpls, User $student): ?array
    {
        // Only relevant for MYP students in Curriculum Semester 2
        if (!$attributes->isMyp() || $attributes->getCurriculumSemester() !== 2) {
            return null;
        }

        $falWorkTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS);

        $fal = new FinalAchievementLevels(
            $unitImpls,
            $student,
            $this->visibility,
            $this->getUser(),
            \is_numeric($falWorkTypeId) ? (int)$falWorkTypeId : null,
            $this->rubricMapper,
            $this->getAuthorizer(),
            $this->getRouter()
        );

        return $fal->toContext();
    }


    /**
     * Builds IB Grade Graph report data.
     *
     * @param StudentAttributes $attributes
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @return null|array
     * @throws Filter\BrokenFilterException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function buildInternationalBaccalaureateGraphData(StudentAttributes $attributes, CourseSpec\Model $courseSpec, User $student): ?array
    {
        // Only relevant for:
        // * MYP-S2
        // * DP-Y11-S2, DP-Y12-S1 + DP-Y12-S2
        $relevant =
            ($attributes->isMyp() && 2 === $attributes->getCurriculumSemester()) ||
            ($attributes->isDp() && (12 === $attributes->getCurriculumYearLevel() || 2 === $attributes->getCurriculumSemester()));

        if (!$relevant) {
            return null;
        }

        if ($attributes->isMyp() && 2 === $attributes->getCurriculumSemester()) {
            $workTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS);
            $workTypeId = \is_numeric($workTypeId) ? (int)$workTypeId : null;

            if (null === $workTypeId) {
                $errorMsg    = 'Cannot render MYP Report components. Work type not set.';
                $routeConfig = $this->getRouteIfAuthorized('tigsSummativeAdmin.getAdmin');
                // If user is permitted, prompt them to fix this.
                if ($routeConfig) {
                    $hash     = Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS;
                    $errorMsg .= " <a href =\"{$routeConfig}#{$hash}\">MYP Final Achievement Levels</a>";
                }
                return ['error' => $errorMsg];
            }
        } else {
            $workTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE);
            $workTypeId = \is_numeric($workTypeId) ? (int)$workTypeId : null;

            if (null === $workTypeId) {
                $errorMsg    = 'Cannot render DP Report components. Work type not set.';
                $routeConfig = $this->getRouteIfAuthorized('tigsSummativeAdmin.getAdmin');
                // If user is permitted, prompt them to fix this.
                if ($routeConfig) {
                    $hash     = Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE;
                    $errorMsg .= " <a href =\"{$routeConfig}#{$hash}\">Set DP Report Work Type</a>";
                }
                return ['error' => $errorMsg];
            }
        }

        $cohortUnitImpls = $this->findCohortUnitImpls($courseSpec);

        // TODO filter to assessment due in the report's time period
        $filters = [
            Filters\Assessment\NullableContext\UnitImpl\Definition::class => $cohortUnitImpls->getPropertyValues('id'),
            Filters\Assessment\WorkType\Definition::class                 => [$workTypeId],
        ];

        $assessables = $this->assessmentMapperProxy->findAllByIds($this->assessmentIdFinder->findIdsByFilters($filters));

        foreach ($assessables as $assessable) {
            $unitImpl                                                                        = $assessable->getBaseContext();
            $baseAssessable                                                                  = $assessable->getBaseAssessable();
            $cohortUnitImpls[$unitImpl->getId()]->getAssessables()[$baseAssessable->getId()] = $baseAssessable;
        }

        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessables);

        $ib = new InternationalBaccalaureateGraph(
            $cohortUnitImpls,
            $student,
            $attributes,
            $this->visibility,
            $this->getUser(),
            $workTypeId,
            $this->rubricMapper,
            $attributes->getCurriculumYearLevel(),
            $attributes->getCurriculumSemester(),
            $this->getAuthorizer(),
            $this->getRouter()
        );

        return $ib->toContext();
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
}
