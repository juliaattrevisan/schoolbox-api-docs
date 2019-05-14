<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Students;

use External_ClassInterface;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
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

    /**
     * StudentLists constructor.
     *
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param Model                   $folder
     * @param LogMapper               $logMapper
     */
    public function __construct(
        External_ClassInterface $externalClass,
        UserMapper $userMapper,
        Folder\Model $folder,
        LogMapper $logMapper
    ) {

        $this->externalClass    = $externalClass;
        $this->userMapper       = $userMapper;
        $this->logMapper        = $logMapper;
        $this->folder           = $folder;
    }

    /**
     * Get the lists of students in the folder
     *
     * @return array
     * @throws Exception
     */
    public function getStudentLists(): array
    {
        $students = $this->externalClass->getStudents($this->folder->getCodeString());
        foreach ($students as $key => $student) {
            $user = $this->userMapper->findByExternalId($student['synergy_id']);
            $lastVisit = $this->logMapper->lastVisit($this->folder, $user)->first();

            $students[$key]['id']                   = $user->getId();
            $students[$key]['email']                = $user->getEmail();
            $students[$key]['ePortfolioIsEnabled']  = $user->getEportfolioEnable();
            $students[$key]['lastAccessTime']       = $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never';
        }

        return $students;
    }
}
