<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      hhttps://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Students;

use External_ClassInterface;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper as LogMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
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

    /** @var string */
    private $classCode;

    /** @var LogMapper */
    private $logMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /**
     * StudentLists constructor.
     *
     * @param External_ClassInterface $externalClass
     * @param UserMapper              $userMapper
     * @param FolderMapper            $folderMapper
     * @param LogMapper               $logMapper
     */
    public function __construct(External_ClassInterface $externalClass,
                                UserMapper $userMapper,
                                FolderMapper $folderMapper,
                                LogMapper $logMapper
    ) {

        $this->externalClass    = $externalClass;
        $this->userMapper       = $userMapper;
        $this->logMapper        = $logMapper;
        $this->folderMapper     = $folderMapper;
    }

    /**
     * @param string $classCode
     *
     * @return array
     * @throws Exception
     */
    public function getStudentLists(string $classCode): array
    {
        $students = $this->externalClass->getStudents($classCode);
        $folder   = $this->folderMapper->findByCode($classCode);

        foreach ($students as $key => $student) {
            $user = $this->userMapper->findByExternalId($student['synergy_id']);
            $lastVisit = $this->logMapper->lastVisit($folder, $user)->first();

            $students[$key]['id']                   = $user->getId();
            $students[$key]['email']                = $user->getEmail();
            $students[$key]['ePortfolioIsEnabled']  = $user->getEportfolioEnable();
            $students[$key]['lastAccessTime']       = $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never';
        }

        return $students;
    }
}
