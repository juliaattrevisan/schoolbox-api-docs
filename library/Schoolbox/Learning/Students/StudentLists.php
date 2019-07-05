<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Students;

use Aura\Router\Router;
use External_ClassInterface;
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

    private $router;

    private $pastoralIsEnabled;

    /**
     * StudentLists constructor.
     *
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param Folder\Model            $folder
     * @param LogMapper               $logMapper
     * @param Router                  $router
     * @param bool                    $pastoralIsEnabled
     */
    public function __construct(
        External_ClassInterface $externalClass,
        UserMapper $userMapper,
        Model $folder,
        LogMapper $logMapper,
        Router $router,
        bool $pastoralIsEnabled
    ) {

        $this->externalClass        = $externalClass;
        $this->userMapper           = $userMapper;
        $this->logMapper            = $logMapper;
        $this->folder               = $folder;
        $this->router               = $router;
        $this->pastoralIsEnabled    = $pastoralIsEnabled;
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
                    $students[$key]['timetableIsHidden'] = $user->getHideTimetable();
                    $students[$key]['routes'] = [
                        'pastoral' => $this->pastoralIsEnabled ? $this->router->generate('pastoral.getStudent', ['studentId' => $user->getId()]) : '',
                        'duework'  => $this->router->generate('learningUpcoming.getUpcomingForStudent', ['studentId' => $user->getId()]),
                        'grades'   => $this->router->generate('learningGrades.getGrades', ['userId' => $user->getId()]),
                    ];
                } catch (Exception $e) {
                    //no user found
                }
            }
        }

        return $students;
    }
}
