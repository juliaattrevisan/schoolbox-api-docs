<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Students;

use Alaress_Date_Format;
use Aura\Router\Router;
use External_ClassInterface;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper as LogMapper;
use Schoolbox\Resource\Folder\Log\Model as Log;

/**
 * A helper class that's responsible for fetching students by their class codes
 *
 * @package Schoolbox\Learning\Students
 */
class StudentLists
{
    /** @var External_ClassInterface */
    private $externalClass;

    /** @var UserMapper */
    private $userMapper;

    /** @var LogMapper */
    private $logMapper;

    /** @var Folder\Model */
    private $folder;

    /** @var Router */
    private $router;

    /** @var bool */
    private $pastoralIsEnabled;

    /** @var bool */
    private $mailIsEnabled;

    private bool $upcomingAssessmentsEnabled;

    private bool $gradesEnabled;

    /** @var string */
    private $mailHandle;

    /** @var RosettaStone */
    private $rosettaStone;

    /**
     * StudentLists constructor.
     *
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param Model                   $folder
     * @param LogMapper               $logMapper
     * @param Router                  $router
     * @param bool                    $pastoralIsEnabled
     * @param bool                    $mailIsEnabled
     * @param bool                    $upcomingAssessmentsEnabled
     * @param bool                    $gradesEnabled
     * @param string                  $mailHandle
     * @param RosettaStone            $rosettaStone
     */
    public function __construct(
        External_ClassInterface $externalClass,
        UserMapper $userMapper,
        Model $folder,
        LogMapper $logMapper,
        Router $router,
        bool $pastoralIsEnabled,
        bool $mailIsEnabled,
        bool $upcomingAssessmentsEnabled,
        bool $gradesEnabled,
        string $mailHandle,
        RosettaStone $rosettaStone
    ) {

        $this->externalClass              = $externalClass;
        $this->userMapper                 = $userMapper;
        $this->logMapper                  = $logMapper;
        $this->folder                     = $folder;
        $this->router                     = $router;
        $this->pastoralIsEnabled          = $pastoralIsEnabled;
        $this->mailIsEnabled              = $mailIsEnabled;
        $this->upcomingAssessmentsEnabled = $upcomingAssessmentsEnabled;
        $this->gradesEnabled              = $gradesEnabled;
        $this->mailHandle                 = $mailHandle;
        $this->rosettaStone               = $rosettaStone;
    }

    /**
     * Get the lists of students in the folder
     *
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getStudentLists(): array
    {
        // Fetch class list for each class code separately,
        // so that the kids also have their class code(s) added
        $students = [];
        if ($this->folder->getCodes()) {
            foreach ($this->folder->getCodes() as $code) {
                foreach ($this->externalClass->getStudents($code->getCode()) as $studentSisRecord) {
                    // No external ID: there is nothing that can sensibly be shown
                    $externalId = $studentSisRecord['synergy_id'] ?? null;
                    if (isset($externalId)) {
                        $students[$externalId] ??= $this->augmentStudentSisRecord($studentSisRecord);
                        $students[$externalId]['classCodes'][] = $code->getCode();
                    }
                }
            }
        }

        // Now order the students by surname, then given name
        $students = array_values($students);
        usort(
            $students,
            static function (array $a, array $b) {
                $cmp = strnatcasecmp($a['lastname'], $b['lastname']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strnatcasecmp($a['firstname'], $b['firstname']);
            }
        );

        return $students;
    }

    /**
     * Augments student's SIS record with data required by the class list.
     *
     * @param array $studentSisRecord
     * @return array
     * @throws Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    private function augmentStudentSisRecord(array $studentSisRecord): array
    {
        // No external ID: show the SIS data only
        if (empty($studentSisRecord['synergy_id'])) {
            return $studentSisRecord;
        }
        try {
            $user = $this->userMapper->findByExternalId($studentSisRecord['synergy_id'], Role::STUDENT);
        } catch (Exception $e) {
            // no user found: show the SIS data only
            return $studentSisRecord;
        }

        $lastVisit = $this->logMapper->lastVisit($this->folder, $user)->first();

        $studentSisRecord['id']                     = $user->getId();
        $studentSisRecord['email']                  = $user->getEmail();
        $studentSisRecord['lastAccessTime']         = $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never';
        $studentSisRecord['learningMomentsEnabled'] = $user->getLearningMomentsEnabled();
        $studentSisRecord['routes']                 = [];
        $studentSisRecord['classCodes']             = [];

        // Upcoming assessments
        if ($this->upcomingAssessmentsEnabled) {
            $studentSisRecord['routes']['dueWork'] = [
                'label' => $this->rosettaStone->translate('dueWork', false, true),
                'href'  => $this->router->generate('learningUpcoming.getUpcomingForStudent', ['studentId' => $user->getId()]),
                'class' => 'icon-due-work',
                'title' => '',
            ];
        }

        // Grades
        if ($this->gradesEnabled) {
            $studentSisRecord['routes']['grades'] = [
                'label' => $this->rosettaStone->translate('grades', true, true),
                'href'  => $this->router->generate('learningGrades.getGrades', ['userId' => $user->getId()]),
                'class' => 'icon-approve',
                'title' => '',
            ];
        }

        // ePortfolio
        if ($user->getEportfolioEnable()) {
            $studentSisRecord['routes']['ePortfolio'] = [
                'label' => 'ePortfolio',
                'href'  => '/eportfolio/' . $user->getId() . '/profile',
                'class' => 'icon-eportfolio',
                'title' => '',
            ];
        }

        // Pastoral
        if ($this->pastoralIsEnabled) {
            $studentSisRecord['routes']['pastoral'] = [
                'label' => $this->rosettaStone->translate('pastoralCare', false, true),
                'href'  => $this->router->generate('pastoral.getStudent', ['studentId' => $user->getId()]),
                'class' => 'icon-pastoral',
                'title' => '',
            ];
        }

        // Calendar
        $studentSisRecord['routes']['calendar'] = [
            'label' => $this->rosettaStone->translate('calendar', false, true),
            'href'  => $this->router->generate(
                'calendar.getCalendar',
                [
                    'selectedDate' => Alaress_Date_Format::now()->getURLDate(),
                    'userId'       => $user->getId()
                ]
            ),
            'class' => 'icon-calendar',
            'title' => '',
        ];

        // Timetable
        if (!$user->getHideTimetable()) {
            $studentSisRecord['routes']['timetable'] = [
                'label' => 'Timetable',
                'href'  => '/timetable/' . $user->getId(),
                'class' => 'icon-timetable',
                'title' => '',
            ];
        }

        // Email
        if ($this->mailIsEnabled && $user->getEmail()) {
            $studentSisRecord['routes']['email'] = [
                'label' => 'Email',
                'href'  => $this->mailHandle . $user->getEmail(),
                'class' => 'icon-email',
                'title' => 'Send Email',
            ];
        }
        return $studentSisRecord;
    }
}
