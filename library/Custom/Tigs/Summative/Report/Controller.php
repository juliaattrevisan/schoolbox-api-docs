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
use Custom\Tigs\Summative\Report\Components\DiplomaProgrammeGraph;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use External_UserInterface;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use UnexpectedValueException;

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


    /**
     * @param External_UserInterface $externalUser
     * @param Term\Mapper            $termMapper
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricMapper           $rubricMapper
     * @param Access                 $access
     */
    public function __construct(
        External_UserInterface $externalUser,
        Term\Mapper $termMapper,
        CourseImpl\Mapper $courseImplMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricMapper $rubricMapper,
        Access $access
    ) {
        $this->externalUser          = $externalUser;
        $this->termMapper            = $termMapper;
        $this->courseImplMapper      = $courseImplMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
        $this->visibility            = new Visibility($access);
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
        [$isHsc, $isIb, $sbSem, $term, $curriculumYearLevel, $curriculumSemester] = $this->deriveStudentAttributes($user);

        $errors = [];

        $courseImplsWithUnits = $this->courseImplMapper->findByFolderId($folder->getId());

        // Check that multiple course specs have not been imported to this page
        $courseSpecCount = $this->getCourseSpecCount($courseImplsWithUnits);
        if ($courseSpecCount >= 2) {
            // Don't bother loading model graph if we have multiple courses imported to this folder.
            // We can't render the page in this case because we rely on a single course-textbox being available.
            // It may be ok to import the same courseSpec multiple times, because each import may be a discrete subset of units.
            $error = \sprintf('Only 1 course can be imported to the class. Found %d.', $courseSpecCount);
            $route = $this->getRouteIfAuthorized('courseImport.getManagement', ['folderId' => $folder->getId(), 'folder' => $folder]);
            if ($route) {
                $error .= \sprintf(' <a href="%s">Manage Course imports</a>', $route);
            }
            $errors[]  = $error;
            $unitImpls = $aamData = [];
        } else {
            $this->loadParticipation($courseImplsWithUnits);
            $unitImpls = $this->getUnitImpls($courseImplsWithUnits);

            // Build component data
            $aamData = $this->buildAcademicAchievementMatrixData($unitImpls, $user);
        }

        $dpData  = $this->buildDiplomaProgrammeReportData($unitImpls, $user);

        $data   = [
            'user'                => $user,
            'folder'              => $folder,
            'isEnabled'           => $this->getConfigLoader()->get(Config::_TIGS_SUBJECT_REPORT_ENABLED),
            'isHsc'               => $isHsc,
            'isIb'                => $isIb,
            'sbSem'               => $sbSem,
            'term'                => $term,
            'curriculumYearLevel' => $curriculumYearLevel,
            'curriculumSemester'  => $curriculumSemester,
            'errors'              => $errors,
            'unitImpls'           => $unitImpls,
            'aamData'             => $aamData,
            'dpData'              => $dpData,
        ];
        $output = $this->buildHtmlOutput('@tigsSummativeReport/subject.twig', $data, "Subject Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Helper to derive relevant student attributes
     *
     * @param User $user
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function deriveStudentAttributes(User $user): array
    {
        $ibFlagOn = (bool)$this->externalUser->getStudent($user->getExternalId())['StudentIBFlag'];

        $isHsc = !$ibFlagOn || $user->getYear() <= 10;
        $isIb  = $ibFlagOn || $user->getYear() <= 10;

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
        if (4 === $term && (int)$user->getYear() === 11) {
            // Year 11, Term 4 => Sem1 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 1;
        } elseif (2 === $term && (int)$user->getYear() === 12) {
            // Year 12, Term 2 => Sem2 Yr 12 Curriculum
            $curriculumYearLevel = 12;
            $curriculumSemester  = 2;
        } else {
            // Normal Case
            $curriculumYearLevel = $user->getYear();
            $curriculumSemester  = $sbSem;
        }

        return [$isHsc, $isIb, $sbSem, $term, $curriculumYearLevel, $curriculumSemester];
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
        /** @var UnitImpl\Model[] $unitImpls */
        $unitImpls = [];
        foreach ($courseImplsWithUnits as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImpls[$unitImpl->getId()] = $unitImpl;
            }
        }

        foreach ($this->assessmentMapperProxy->findAllByUnitImplementationIdsForCourseOutline(\array_keys($unitImpls)) as $assessable) {
            $unitImpls[$assessable->getBaseContext()->getId()]->getAssessables()[$assessable->getId()] = $assessable;
        }
    }


    /**
     * Returns the number of course specs the course impls were imported from
     *
     * @param GenericCollection|CourseImpl\Model[] $courseImplsWithUnits
     * @return int
     */
    private function getCourseSpecCount(GenericCollection $courseImplsWithUnits): int
    {
        $courseSpecIds = [];
        foreach ($courseImplsWithUnits as $courseImpl) {
            $courseSpecIds[$courseImpl->getSpecification()->getId()] = true;
        }
        return \count($courseSpecIds);
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
     * Builds Middle Years Programme / Diploma Programme (MYP/DP) report data.
     *
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @return array
     */
    private function buildDiplomaProgrammeReportData(GenericCollection $unitImpls, User $student): array
    {
        $dpWorkTypeId = $this->getConfigLoader()->get(Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE);
        $dpWorkTypeId = \is_numeric($dpWorkTypeId) ? (int)$dpWorkTypeId : null;

        if (null === $dpWorkTypeId) {
            $errorMsg = 'Cannot render DP Report components. Work type not set.';
            // If user is superuser, prompt them to fix this.
            if ($this->getUser()->isSuperuser()) {
                $routeConfig = $this->getRouter()->generate('tigsSummativeAdmin.getAdmin');
                $hash        = Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE;
                $errorMsg    .= " <a href =\"{$routeConfig}#{$hash}\">Set DP Report Work Type</a>";
            }
            return ['error' => $errorMsg];
        }

        $dp = new DiplomaProgrammeGraph();

        return $dp->toContext();
    }
}
