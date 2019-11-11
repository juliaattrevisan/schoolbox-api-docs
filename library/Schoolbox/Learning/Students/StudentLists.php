<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Students;

use Aura\Router\Router;
use External_ClassInterface;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Role;
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

    /** @var string */
    private $mailHandle;

    /** @var RosettaStone */
    private $rosettaStone;

    /**
     * StudentLists constructor.
     *
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param Folder\Model            $folder
     * @param LogMapper               $logMapper
     * @param Router                  $router
     * @param bool                    $pastoralIsEnabled
     * @param bool                    $mailIsEnabled
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
        string $mailHandle,
        RosettaStone $rosettaStone
    ) {

        $this->externalClass        = $externalClass;
        $this->userMapper           = $userMapper;
        $this->logMapper            = $logMapper;
        $this->folder               = $folder;
        $this->router               = $router;
        $this->pastoralIsEnabled    = $pastoralIsEnabled;
        $this->mailIsEnabled        = $mailIsEnabled;
        $this->mailHandle           = $mailHandle;
        $this->rosettaStone         = $rosettaStone;
    }

    /**
     * Get the lists of students in the folder
     *
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getStudentLists(): array
    {
        $students = $this->externalClass->getStudents($this->folder->getCodeString());

        foreach ($students as $key => $student) {
            if (!empty($student['synergy_id'])) {
                try {
                    $user = $this->userMapper->findByExternalId($student['synergy_id'], Role::STUDENT);
                    $lastVisit = $this->logMapper->lastVisit($this->folder, $user)->first();

                    $students[$key]['id'] = $user->getId();
                    $students[$key]['email'] = $user->getEmail();
                    $students[$key]['ePortfolioIsEnabled'] = $user->getEportfolioEnable();
                    $students[$key]['lastAccessTime'] = $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never';

                    $students[$key]['routes'] = [
                        // Due work
                        'dueWork' => [
                            'label' => $this->rosettaStone->translate('dueWork', false, true),
                            'href'  => $this->router->generate('learningUpcoming.getUpcomingForStudent', ['studentId' => $user->getId()]),
                            'class' => 'icon-due-work',
                            'title' => '',
                        ],
                        // Grades
                        'grades' => [
                            'label' => $this->rosettaStone->translate('grades', false, true),
                            'href'  => $this->router->generate('learningGrades.getGrades', ['userId' => $user->getId()]),
                            'class' => 'icon-approve',
                            'title' => '',
                        ],
                    ];

                    // ePortfolio
                    if ($user->getEportfolioEnable()) {
                        $students[$key]['routes']['ePortfolio'] = [
                            'label' => 'ePortfolio',
                            'href'  => '/eportfolio/' . $user->getId() . '/profile',
                            'class' => 'icon-eportfolio',
                            'title' => '',
                        ];
                    }

                    // Pastoral
                    if ($this->pastoralIsEnabled) {
                        $students[$key]['routes']['pastoral'] = [
                            'label' => $this->rosettaStone->translate('pastoralCare', false, true),
                            'href'  => $this->router->generate('pastoral.getStudent', ['studentId' => $user->getId()]),
                            'class' => 'icon-pastoral',
                            'title' => '',
                        ];
                    }

                    // Calendar
                    $students[$key]['routes']['calendar'] = [
                        'label' => $this->rosettaStone->translate('calendar', false, true),
                        'href'  => $this->router->generate('calendar.getCalendar', [
                            'selectedDate' => \Alaress_Date_Format::now()->getURLDate(),
                            'userId' => $user->getId()
                        ]),
                        'class' => 'icon-calendar',
                        'title' => '',
                    ];

                    // Timetable
                    if (!$user->getHideTimetable()) {
                        $students[$key]['routes']['timetable'] = [
                            'label' => 'Timetable',
                            'href'  => '/timetable/' .  $user->getId(),
                            'class' => 'icon-timetable',
                            'title' => '',
                        ];
                    }

                    // Email
                    if ($user->getEmail() && $this->mailIsEnabled) {
                        $students[$key]['routes']['email'] = [
                            'label' => 'Email',
                            'href'  => $this->mailHandle . $user->getEmail(),
                            'class' => 'icon-email',
                            'title' => 'Send Email',
                        ];
                    }
                } catch (Exception $e) {
                    //no user found
                }
            }
        }

        return $students;
    }
}
