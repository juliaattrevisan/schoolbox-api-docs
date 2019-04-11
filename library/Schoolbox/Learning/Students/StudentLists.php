<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Students;


use External_Class;

use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper as LogMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Log\Model as Log;

class StudentLists
{

    private $externalClass;

    private $userMapper;

    private $classCode;

    private $logMapper;

    private $folderMapper;

    /**
     * StudentLists constructor.
     *
     * @param External_Class $externalClass
     * @param UserMapper     $userMapper
     * @param FolderMapper   $folderMapper
     * @param LogMapper      $logMapper
     */
    public function __construct(External_Class $externalClass,
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
     * @return $this
     */
    public function setClassCode(string $classCode)
    {
        $this->classCode = $classCode;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClassCode()
    {
        return $this->classCode;
    }

    /**
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getStudentLists()
    {
        $students = $this->externalClass->getStudents($this->getClassCode());
        $folder   = $this->folderMapper->findByCode($this->getClassCode());

        foreach ($students as $key => $student) {
            $user = $this->userMapper->findByExternalId($student['synergy_id']);
            $lastVisit = $this->logMapper->lastVisit($folder, $user)->first();

            $students[$key]['id']               = $user->getId();
            $students[$key]['email']            = $user->getEmail();
            $students[$key]['lastAccessTime']   = $lastVisit instanceof Log ? $lastVisit->getLastAccessTime()->getShort() : 'Never';
        }

        return $students;
    }
}
