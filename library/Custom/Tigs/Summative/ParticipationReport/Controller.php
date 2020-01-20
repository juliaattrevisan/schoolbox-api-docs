<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use DomainException;
use External_ClassInterface;
use function implode;
use LogicException;
use Schoolbox\Assessment\Rubric\DataModel as RubricDataModel;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Filter\Filters;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Resource\Textbox;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use External_UserInterface;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use External_User;
use UnderflowException;
use UnexpectedValueException;
use Alaress\Database;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;

class Controller extends Web\Controller\Base
{
    /** @var IConnector */
    private $database;

    /** @var External_UserInterface|External_User */
    private $externalUser;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var UserMapper */
    private $userMapper;

    /** IConnector $externalDatabase */
    private $externalDatabase;

    /** @var PastoralMapper */
    private $pastoralMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var Textbox\Mapper */
    private $textBoxMapper;

    /** @var Access */
    private $access;

    /** @var Assessment\Mapper */
    private $assessmentMapper;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var RubricDataModel\DataSource */
    private $rubricMapper;


    /**
     * @param IConnector             $database
     * @param External_UserInterface $externalUser
     * @param External_ClassInterface $externalClass
     * @param CourseImpl\Mapper      $courseImplMapper
     * @param UserMapper             $userMapper
     * @param PastoralMapper         $pastoralMapper
     * @param Folder\Mapper          $folderMapper
     * @param Textbox\Mapper         $textBoxMapper
     * @param Access                 $access
     * @param IConnector             $externalDatabase
     * @param Assessment\Mapper      $assessmentMapper
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @param RubricDataModel\DataSource $rubricMapper
     */
    public function __construct(
        IConnector $database,
        External_UserInterface $externalUser,
        External_ClassInterface $externalClass,
        CourseImpl\Mapper $courseImplMapper,
        UserMapper $userMapper,
        PastoralMapper $pastoralMapper,
        Folder\Mapper $folderMapper,
        Textbox\Mapper $textBoxMapper,
        Access $access,
        IConnector $externalDatabase,
        Assessment\Mapper $assessmentMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        RubricDataModel\DataSource $rubricMapper
    ) {
        $this->database              = $database;
        $this->externalUser          = $externalUser;
        $this->externalClass         = $externalClass;
        $this->courseImplMapper      = $courseImplMapper;
        $this->userMapper            = $userMapper;
        $this->externalDatabase      = $externalDatabase;
        $this->pastoralMapper        = $pastoralMapper;
        $this->folderMapper          = $folderMapper;
        $this->textBoxMapper         = $textBoxMapper;
        $this->access                = $access;
        $this->assessmentMapper      = $assessmentMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricMapper          = $rubricMapper;
    }


    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/participation/user/{userId}
     * @since 18.1.0
     * @param User        $user
     * @param string|null $reportDate
     * @throws InvalidColumnException
     * @throws DefinitionException
     * @throws DomainException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    public function getReport(User $user, ?string $reportDate): void
    {
        $externalStudent = $this->externalUser->getStudent($user->getExternalId());
        $attributes      = new StudentAttributes($this->getConfigLoader(), $user, $externalStudent['StudentIBFlag'], $reportDate);
        $roleTypeOverride = $this->request->query->get('role-type');

        $deanAndMentor = $this->getDeanAndMentor($user, $externalStudent);
        $awards        = $this->getAwardsData($user, $attributes);
        $houseEvents   = $this->getHouseEventsData($user, $attributes);
        $absences      = $this->getAbsenceData($user, $attributes);
        $subjectList   = $this->getSubjectList($user, $attributes, $reportDate);
        $academicTranscript = $this->getAcademicTranscript($user, $attributes, $subjectList, $roleTypeOverride);
        $deansComments = $this->getDeansComment($user, $attributes);
        $explanation   = $this->getExplanation($attributes, (bool)$this->getRequest()->query->get('inline'));

        $data = [
            'user'               => $user,
            'isEnabled'          => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
            'attributes'         => $attributes,
            'dean'               => $deanAndMentor['dean'],
            'mentor'             => $deanAndMentor['mentor'],
            'awards'             => $awards,
            'houseEvents'        => $houseEvents,
            'absences'           => $absences,
            'academicTranscript' => $academicTranscript,
            'subjects'           => $subjectList,
            'deansComments'      => $deansComments,
            'explanation'        => $explanation,
            'reportDate'         => $reportDate,
            'dataAttributeError' => Config::ERROR_DATA_ATTRIBUTE,
            'showErrors'         => ($roleTypeOverride ?? $this->getUser()->getRole()->getType()) === Role::STAFF,
        ];

        $output = $this->buildHtmlOutput('@tigsParticipationReport/participation.twig', $data, "Participation Report");

        $this->getResponse()->content->set($output);
    }


    /**
     * Returns arrays of data for the student's Dean and Mentor
     *
     * @param User  $user
     * @param array $externalStudent
     * @return User[]
     */
    private function getDeanAndMentor(User $user, array $externalStudent): array
    {
        if ('schoolbox' === $this->getConfigLoader()->get('external_type')) {
            // This is just to enable testing on our TIGS test dataset, which uses the schoolbox connector.
            // The client (TIGS) uses the Synergetic connector.
            // The optimal solution would be to implement the yearLevel and tutor/mentor relationships in the
            // Schoolbox connector and populate the TIGS test dataset, but there's issues with their implementation,
            // and we don't have time to address them now. So we'll just hack in some dummy data for now.
            switch ($user->getYear()) {
                case 12:
                    $externalIdDean = '8';
                    $deanFullName   = 'Ms Celia Glenn';
                    break;
                case 11:
                    $externalIdDean = '483';
                    $deanFullName   = 'Mr Trevor Reese';
                    break;
                case 10:
                    $externalIdDean = '227';
                    $deanFullName   = 'Mr Ryan Vollmer';
                    break;
                default:
                    $externalIdDean = 'INVALID';
                    $deanFullName   = null;
            }
            $externalIdMentors = [
                '485',   // Alaress Admin
                '494',   // Ms Melissa Brock
                '323',   // Mrs Jenifer Shadbolt
                '194',   // Mrs Pauline Tillman
                '2',     // Miss Myra McCorvey
                '5',     // Miss Greta Neville
                '7',     // Mr Angelo Papanikolas
                'INVALID',
                'INVALID',
                'INVALID',
            ];
            $externalIdMentor  = $externalIdMentors[$user->getId() % \count($externalIdMentors)];
        } else {
            // Synergetic contains Dean's id + name
            $externalIdDean = $externalStudent['YearLevelCoordID'];
            $deanFullName   = $externalStudent['StudentYearLevelCoordinator'];
            // Synergetic contains mentor's id, but not name
            $externalIdMentor = $externalStudent['TeacherTutorID'];
        }

        // Only want staff (staff and parent may share same external ID)
        $deanAndMentor = $this->userMapper->findByColumnList(['synergy_id' => [$externalIdDean, $externalIdMentor], 'g.group_type' => Role::STAFF]);

        $dean = $mentor = null;
        foreach ($deanAndMentor as $deanOrMentor) {
            // Can't break, as Dean and Mentor may be same user
            if ($deanOrMentor->getExternalId() === $externalIdDean) {
                $dean = $deanOrMentor;
            }
            if ($deanOrMentor->getExternalId() === $externalIdMentor) {
                $mentor = $deanOrMentor;
            }
        }

        // Did we find a Dean in the SIS, but not in SB?
        if ($externalIdDean && !$dean) {
            $dean = (new User())->setExternalId($externalIdDean)->setFirstname($deanFullName);
        }

        // Did we find a Mentor in the SIS, but not in SB?
        if ($externalIdMentor && !$mentor) {
            $mentor = (new User())->setExternalId($externalIdMentor);
        }

        return [
            'dean'   => $dean,
            'mentor' => $mentor,
        ];
    }


    /**
     * Returns awards data for the student given within the reports period
     *
     * @param User              $user
     * @param StudentAttributes $attributes
     * @return GenericCollection|Award[]
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    private function getAwardsData(User $user, StudentAttributes $attributes): GenericCollection
    {
        $sql = "
            SELECT Award, Detail
            FROM uvContinuousReportingAwards
            WHERE StudentID = :student_id
            AND GivenDate >= :start AND GivenDate <= :finish
            ORDER BY GivenDate ASC
        ";

        $params = [
            'student_id' => $user->getExternalId(),
            'start'      => $attributes->getCurriculumYearStart()->getDatabase(),
            'finish'     => $attributes->getCurriculumYearFinish()->getDatabase(),
        ];

        $awards = new GenericCollection(Award::class);

        $query = $this->externalDatabase->executeQuery($sql, $params);

        /** @noinspection PhpAssignmentInConditionInspection */
        while($row = $query->fetch()){
            $awards->add(new Award($row['Award'], $row['Detail']));
        }

        return $awards;
    }


    /**
     * Returns awards data for the student given within the reports period
     *
     * @param User              $user
     * @param StudentAttributes $attributes
     * @return GenericCollection|Award[]
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    private function getHouseEventsData(User $user, StudentAttributes $attributes): GenericCollection
    {
        $sql = "
            SELECT Event, Status
            FROM uvContinuousReportingHouseParticipation
            WHERE ID = :external_id
            AND date >= :start AND date <= :finish
            ORDER BY date ASC
        ";

        $params = [
            'external_id'  => $user->getExternalId(),
            'start'        => $attributes->getCurriculumYearStart()->getDatabaseDate(),
            'finish'       => $attributes->getCurriculumYearFinish()->getDatabaseDate(),
        ];

        $houseEvents = new GenericCollection(HouseEvent::class);

        $query = $this->externalDatabase->executeQuery($sql, $params);

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $query->fetch()) {
            $houseEvents->add(new HouseEvent($row['Event'], $row['Status']));
        }

        return $houseEvents;
    }


    /**
     * Returns absence data for the student given within the reports period
     *
     * @param User              $user
     * @param StudentAttributes $attributes
     * @return array
     * @throws Database\Exception
     */
    private function getAbsenceData(User $user, StudentAttributes $attributes): array
    {
        $semesters = $attributes->getCurriculumSemesters();
        $start1    = $semesters[1]->getStart();
        $finish1   = $semesters[1]->getFinish();
        $start2    = $semesters[2]->getStart();
        $finish2   = $semesters[2]->getFinish();

        $pb1 = $this->externalDatabase->buildPredicateBuilder('t1');
        $pb1->equals('t1.ID', $user->getExternalId())
            ->greaterThanOrEqualTo('t1.date', $start1->getDatabaseDate())
            ->lessThanOrEqualTo('t1.date', $finish1->getDatabaseDate());

        $pb2 = $this->externalDatabase->buildPredicateBuilder('t2');
        $pb2->equals('t2.ID', $user->getExternalId())
            ->greaterThanOrEqualTo('t2.date', $start2->getDatabaseDate())
            ->lessThanOrEqualTo('t2.date', $finish2->getDatabaseDate());

        $whereClause1 = implode(' AND ', $pb1->getWhere());
        $whereClause2 = implode(' AND ', $pb2->getWhere());
        $sql          = "
            SELECT 1 AS s, COUNT(*) AS c
            FROM uvContinuousReportingAbsences AS t1
            WHERE {$whereClause1}
              UNION
            SELECT 2 AS s, COUNT(*) AS c
            FROM uvContinuousReportingAbsences AS t2
            WHERE {$whereClause2}
        ";

        $rows = $this->externalDatabase->executeQuery($sql, \array_merge($pb1->getParams(), $pb2->getParams()))->fetchAll();
        $absenceCounts = [];
        foreach ($rows as $row) {
            $absenceCounts[$row['s']] = (int)$row['c'];
        }

        return $absenceCounts;

    }


    /**
     * Returns data suitable for rendering the 'Academic Transcript' component
     *
     * @param User              $student
     * @param StudentAttributes $attributes
     * @param array             $subjectList
     * @param string|null       $roleTypeOverride
     * @return array
     * @throws InvalidColumnException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws RouteNotFound
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws UnderflowException
     * @throws Database\Exception
     */
    private function getAcademicTranscript(User $student, StudentAttributes $attributes, array $subjectList, ?string $roleTypeOverride): array
    {
        $at = new AcademicTranscript(
            $this->getConfigLoader(),
            $this->folderMapper,
            $this->externalUser,
            $this->externalClass,
            $this->courseImplMapper,
            $this->assessmentMapperProxy,
            $this->rubricMapper,
            $this->userMapper,
            $this->assessmentMapper,
            $this->textBoxMapper,
            $this->access,
            $this->database,
            $this->getAuthorizer(),
            $this->getRouter()
        );

        return $at->toContext($this->getUser(), $student, $attributes, $subjectList, $roleTypeOverride);
    }


    /**
     * Returns subject list for report
     *
     * @param User              $user
     * @param StudentAttributes $attributes
     * @param null|string       $reportDate
     * @return array
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    private function getSubjectList(User $user, StudentAttributes $attributes, ?string $reportDate): array
    {
        $fetcher    = new FolderFetcher($this->folderMapper, $this->externalUser);
        $folders    = $fetcher->findForStudent($user, $attributes, $reportDate, $this->getConfigLoader()->get(Config::_TIGS_EXCLUDED_CLASS_CODES));

        $subjects = [];
        foreach ($folders as $folder) {
            $subjects[] = [
                'name'     => $folder->getName(),
                'codes'    => $folder->getCodes()->getPropertyValues('code'),
                'folderId' => $folder->getId(),
            ];
        }

        return $subjects;
    }


    /**
     * Returns general comment of pastoral records of given attributes.
     * @param User              $user
     * @param StudentAttributes $attributes
     * @return array semester numbers -> comments
     * @throws InvalidColumnException
     */
    private function getDeansComment(User $user, StudentAttributes $attributes): array
    {
        $semesters = $attributes->getCurriculumSemesters();

        // initialise
        $deansComment = [];
        foreach ($semesters as  $semester) {
            $deansComment[$semester->getOrdinal()] = '';
        }

        $pastoralType = $this->getConfigLoader()->get(Config::_TIGS_PASTORAL_TYPE);
        if (!$pastoralType) {
            // no pastoral type configured
            return $deansComment;
        }

        // get records in the curriculum year
        $pb = $this->pastoralMapper->getPredicateBuilder()
            ->equals('student_id', $user->getId())
            ->equals('type_id', $pastoralType)
            ->greaterThanOrEqualTo('created_at', $attributes->getCurriculumYearStart()->getDatabase())
            ->lessThanOrEqualTo('created_at', $attributes->getCurriculumYearFinish()->getDatabase());
        $records = $this->pastoralMapper->findByPredicates($pb, ['order' => ['created_at' => 'desc']]);

        // get latest record in each semester
        foreach ($semesters as $semester) {
            $start = $semester->getStart();
            $finish = $semester->getFinish();

            $firstRecord = $records->filter(
                function (Pastoral $record) use ($start, $finish) {
                    return $record->getCreatedAt()->gte($start) && $record->getCreatedAt()->lte($finish);
                }
            )->first();
            if ($firstRecord) {
                $deansComment[$semester->getOrdinal()] = $firstRecord->getBodyPublic();
            }
        }
        return $deansComment;
    }


    /**
     * Returns explanation of grade which matches the given attribute.
     *
     * Will either be link to homepage, or inline content, depending of flag
     *
     * @param StudentAttributes $attributes
     * @param bool              $renderExplanationOfGradesInline
     * @return null|string
     * @throws InvalidColumnException
     */
    private function getExplanation(StudentAttributes $attributes, bool $renderExplanationOfGradesInline): ?string
    {
        $textboxHeading = $this->getConfigLoader()->get(Config::_TIGS_TEXTBOX_HEADING_EXPLANATION_OF_GRADES);
        if (null === $textboxHeading) {
            return null;
        }
        $homepageId = $this->getExplanationOfGradesHomepageId($attributes);
        if (null === $homepageId) {
            return null;
        }

        $pb = $this->textBoxMapper->getPredicateBuilder()
                                  ->equals('categoryId', $homepageId)
                                  ->equals('heading', $textboxHeading);

        $textBoxes = $this->textBoxMapper->findByPredicates($pb, ['order' => ['textBoxID' => 'ASC'],]);

        if (0 === $textBoxes->count()) {
            // Render no link or inline content
            return null;
        }

        if (!$renderExplanationOfGradesInline) {
            // Render link to homepage
            return "Explanation of grades for {$attributes->getExplanationOfGradesType()} can be found <a href=\"/homepage/$homepageId\">here.</a>";
        }

        // Render content of first-textbox on homepage
        /** @var Textbox\Model $firstTextbox */
        $firstTextbox = $textBoxes->first();

        return $firstTextbox->getBody();
    }


    /**
     * Returns the configured 'explanation of grades' homepage id for the specified student
     *
     * @param StudentAttributes $attributes
     * @return string|null
     */
    private function getExplanationOfGradesHomepageId(StudentAttributes $attributes): ?string
    {
        switch ($attributes->getYearLevel()) {
            case 12:
                if ($attributes->isHsc()) {
                    return $this->getConfigLoader()->get(Config::_TIGS_GRADES_INFO_YEAR_12_HSC);
                }
                return $this->getConfigLoader()->get(Config::_TIGS_GRADES_INFO_YEAR_12_IB);
            case 11:
                if ($attributes->isHsc()) {
                    return $this->getConfigLoader()->get(Config::_TIGS_GRADES_INFO_YEAR_11_HSC);
                }
                return $this->getConfigLoader()->get(Config::_TIGS_GRADES_INFO_YEAR_11_IB);
            default:
                return $this->getConfigLoader()->get(Config::_TIGS_GRADES_INFO_YEAR_7_10);
        }
    }
}
