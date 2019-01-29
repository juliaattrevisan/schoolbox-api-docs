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
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Filter\Filters;
use External_UserInterface;
use Schoolbox\UserMapper;
use InvalidArgumentException;
use External_User;

class Controller extends Web\Controller\Base
{

    /** @var External_UserInterface|External_User */
    private $externalUser;

    /** @var UserMapper */
    private $userMapper;

    /** IConnector $externalDatabase */
    private $externalDatabase;


    /**
     * @param External_UserInterface $externalUser
     * @param UserMapper             $userMapper
     * @param IConnector             $externalDatabase
     */
    public function __construct(External_UserInterface $externalUser, UserMapper $userMapper, IConnector $externalDatabase)
    {
        $this->externalUser = $externalUser;
        $this->userMapper   = $userMapper;
        $this->externalDatabase = $externalDatabase;
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

        $data   = [
            'user'       => $user,
            'isEnabled'  => $this->getConfigLoader()->get(Config::_TIGS_PARTICIPATION_REPORT_ENABLED),
            'attributes' => $attributes,
            'dean'       => $deanAndMentor['dean'],
            'mentor'     => $deanAndMentor['mentor'],
            'awards'     => $awards,
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
     * @return array
     */
    private function getAwardsData(User $user, StudentAttributes $attributes): array
    {
        $sql = "
            SELECT Award, Detail, GivenDate
            FROM uvContinuousReportingAwards
            WHERE StudentID = :student_id
            AND GivenDate >= :start AND GivenDate < :finish
            ORDER BY GivenDate ASC
        ";

        // TODO this is wrong for Semester 2 because the participation report, unlike the Subject Report, covers both Sem1 and Sem2
        // TODO Need to update getReportStart/Finish to be getCurriculumSemesterStart/Finish + getCurriculumYearStart/Finish
        $start = $attributes->getCurriculumSemesterStart();
        if ($attributes->getCurriculumSemester() === 2 && $attributes->getCurriculumYearLevel() !== 11) {
            // TODO Just a rough hack for now to move the start back by 6 months if we're in the second semester.
            // TODO We exclude Year 11 as their Sem2 only runs for a single Semester
            $start->addDays(-365 / 2);
        }

        $finish = $attributes->getCurriculumSemesterFinish();
        if ($attributes->getCurriculumSemester() === 1) {
            // TODO Just a rough hack for now to move the finish forward by 6 months if we're in the first semester.
            $finish->addDays(365 / 2);
        }

        $params = [
            'student_id' => $user->getExternalId(),
            'start'      => $start->getDatabaseDate(),
            'finish'     => $finish->getDatabaseDate(),
        ];

        return $this->externalDatabase->executeQuery($sql, $params)->fetchAll();
    }
}
