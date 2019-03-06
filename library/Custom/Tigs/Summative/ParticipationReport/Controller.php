<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Alaress\Database\Connector\IConnector;
use Alaress\Web;
use Aura\Di\Exception\ServiceNotFound;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Filter\Filters;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use External_UserInterface;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use External_User;
use UnexpectedValueException;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface|External_User */
    private $externalUser;

    /** @var UserMapper */
    private $userMapper;

    /** IConnector $externalDatabase */
    private $externalDatabase;

    /** @var PastoralMapper $pastoralMapper */
    private $pastoralMapper;

    /**
     * @param External_UserInterface $externalUser
     * @param UserMapper             $userMapper
     * @param IConnector             $externalDatabase
     * @param PastoralMapper         $pastoralMapper
     */
    public function __construct(External_UserInterface $externalUser, UserMapper $userMapper, IConnector $externalDatabase, PastoralMapper $pastoralMapper)
    {
        $this->externalUser     = $externalUser;
        $this->userMapper       = $userMapper;
        $this->externalDatabase = $externalDatabase;
        $this->pastoralMapper   = $pastoralMapper;
    }


    /**
     * Displays a student's participation report for current term
     *
     * @api   GET /modules/custom/tigs/summative/participation/user/{userId}
     * @level 18.1.0
     * @param User $user
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function getReport(User $user): void
    {
        $externalStudent = $this->externalUser->getStudent($user->getExternalId());

        $attributes = new StudentAttributes(
            $user,
            $externalStudent['StudentIBFlag'],
            $this->getRequest()->query->get('now')
        );

        $deanAndMentor = $this->getDeanAndMentor($user, $externalStudent);
        $awards        = $this->getAwardsData($user, $attributes);
        $houseEvents   = $this->getHouseEventsData($user, $attributes);
        $absences      = $this->getAbsenceData($user, $attributes);
        $deansComments = $this->getDeansComment($user, $attributes);
        $explanation   = $this->getExplanation($attributes);

        $data   = [
            'user'          => $user,
            'isEnabled'     => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
            'attributes'    => $attributes,
            'dean'          => $deanAndMentor['dean'],
            'mentor'        => $deanAndMentor['mentor'],
            'awards'        => $awards,
            'houseEvents'   => $houseEvents,
            'absences'      => $absences,
            'deansComments' => $deansComments,
            'explanation'   => $explanation,
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
     * @throws \Schoolbox\Mapper\Exception
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
            'start'      => $attributes->getCurriculumYearStart()->getDatabaseDate(),
            'finish'     => $attributes->getCurriculumYearFinish()->getDatabaseDate(),
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
     */
    private function getAbsenceData(User $user, StudentAttributes $attributes): array
    {
        $semesters = $attributes->getCurriculumSemesters();
        /** @var \Alaress_Date_Format $start1 */
        $start1 = $semesters[1]['start'];
        /** @var \Alaress_Date_Format $finish1 */
        $finish1 = $semesters[1]['finish'];
        /** @var \Alaress_Date_Format $start2 */
        $start2 = $semesters[2]['start'];
        /** @var \Alaress_Date_Format $finish2 */
        $finish2 = $semesters[2]['finish'];

        $pb1 = $this->externalDatabase->buildPredicateBuilder('t1');
        $pb1->equals('t1.ID', $user->getExternalId())
            ->greaterThanOrEqualTo('t1.date', $start1->getDatabaseDate())
            ->lessThanOrEqualTo('t1.date', $finish1->getDatabaseDate());

        $pb2 = $this->externalDatabase->buildPredicateBuilder('t2');
        $pb2->equals('t2.ID', $user->getExternalId())
            ->greaterThanOrEqualTo('t2.date', $start2->getDatabaseDate())
            ->lessThanOrEqualTo('t2.date', $finish2->getDatabaseDate());

        $whereClause1 = \implode(' AND ', $pb1->getWhere());
        $whereClause2 = \implode(' AND ', $pb2->getWhere());
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
     * Returns general comment of pastoral records of given attributes.
     * @param User $user
     * @param StudentAttributes $attributes
     * @return array semester numbers -> comments
     */
    private function getDeansComment(User $user, StudentAttributes $attributes): array
    {
        $semesters = $attributes->getCurriculumSemesters();

        // initialise
        $deansComment = [];
        foreach (\array_keys($semesters) as $semester) {
            $deansComment[$semester] = '';
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
        foreach ($semesters as $semester => $dates) {
            /** @var \Alaress_Date_Format $start */
            $start = $dates['start'];
            /** @var \Alaress_Date_Format $finish */
            $finish = $dates['finish'];

            $firstRecord = $records->filter(
                function (Pastoral $record) use ($start, $finish) {
                    return $record->getCreatedAt()->gte($start) && $record->getCreatedAt()->lte($finish);
                }
            )->first();
            if ($firstRecord) {
                $deansComment[$semester] = $firstRecord->getBodyPublic();
            }
        }
        return $deansComment;
    }


    /**
     * Returns explanation of grade which matches the given attribute
     * @param StudentAttributes $attributes
     * @return string
     */
    private function getExplanation(StudentAttributes $attributes): string
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
