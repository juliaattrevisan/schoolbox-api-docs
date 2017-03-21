<?php
/**
 * StudentList
 *
 * Get a collection of schoolbox user accounts who are currently
 * associated with, or have previously been associated with, the
 * specified folder and unit
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Resource\Folder\Model as Folder;
use Config;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Group;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation;

class StudentList {

    /**
     * Folder instance
     *
     * @var Folder
     */
    private $folder;

    /**
     * Unit instance
     *
     * @var Implementation\Unit\Model
     */
    private $unitImplementation;

    /**
     * UserMapper
     *
     * @var UserMapper
     */
    private $userMapper;

    /**
     * A collection of User instances
     *
     * @var Collection|User[]
     */
    private $students;

    /**
     * A list of Schoolbox user ids who were previously
     * associated with $folder and $unit but are no longer
     * in it
     *
     * @var array
     */
    private $removedStudentIdList = [];

    /**
     * Constructor
     *
     * @param Folder                    $folder
     * @param Implementation\Unit\Model $unit
     */
    public function __construct(Folder $folder, Implementation\Unit\Model $unit) {
        $this->folder = $folder;
        $this->unitImplementation = $unit;
        $this->userMapper = UserMapper::getInstance();

        $this->process();
    }

    /**
     * Get list of students for this class, and group if it has one.
     */
    private function process()
    {
        $code = $this->folder->getCodeString();

        /* A list of schoolbox user ids corresponding to everyone in $students
         */
        $classStudentIds   = [];

        /* A list of schoolbox accounts who have been added to this folder.
         * This may not be exactly the same as $students due to students arriving and leaving
         */
        $groupStudentIds   = [];

        /* A list of students who have participated in this folder but have since been removed
         */
        $removedStudentIds = [];

        /* From external db, get a list of students associated with the current folder's code
         */
        if ($code && Config::get('external_enable')) {
            $classList = (new \External_Class())->getStudents($code) ?: [];

            /* Get a list of externalIds
             */
            if (count($classList)) {
                $externalIdList = array_map(
                    function ($student) {
                        return $student['synergy_id'];
                    },
                    $classList
                );

                /* With those externalIds, get a list of Schoolbox user ids
                 */
                $classStudentIds = $this->userMapper->findIdsByColumn('synergy_id', $externalIdList);
            }
        }

        /* A list of students involved in this folder, as
         * known by schoolbox
         */
        $groupList = (new Group())->getMemberList($this->folder->getId()) ?: [];

        /* Uniquify results
         */
        if (count($groupList)) {
            $groupStudentIds = array_unique(
                array_map(
                    function($student) {
                        return $student['uid'];
                    },
                    $groupList
                )
            );
        }

        /* Try loading all the students who have completed a assessment for this group, but
         * may no longer be in the class.
         */
        foreach ($this->unitImplementation->getAssessables() as $assessment) {
            foreach ($assessment->getAllParticipants() as $participant) {

                $studentId = $participant->getUser()->getId();

                if (!(in_array($studentId, $classStudentIds, true) || in_array($studentId, $groupStudentIds, true))) {
                    $removedStudentIds[] = $studentId;
                }
            }
        }

        $this->removedStudentIdList = $removedStudentIds;

        /* Finally get a list of all the schoolbox user accounts who
         * are currently, or have been previously, associated with
         * this folder and unit
         */
        $userCollection = $this->userMapper->findByColumn(
            'id',
            array_merge($classStudentIds, $groupStudentIds, $removedStudentIds),
            array('order' => array('lastname' => 'asc', 'firstname' => 'asc'))
        );

        $this->students = $userCollection;
    }
}
