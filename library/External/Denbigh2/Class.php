<?php
/**
 * External_Denbigh2_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Denbigh2_Class implements External_ClassInterface {

    /**
     * Get a list of students in a particular $classCode
     *
     * lastname,
     * firstname,
     * yearlevel,
     * StudentHouseDescription as house,
     * StudentMedicalAlert,
     * StudentSpecialNeedsFlag,
     * synergy_id,
     * ClassCampus
     *
     * @param $classCode
     * @return array
     */
    public function getStudents($classCode)
    {
        $students = array();

        $api = new External_Denbigh2_Api('Enrolments',
            array(
                'Class' => $classCode
            ),
            array(
              'RFMmax' => 0
            )
        );

        if (!$api->hasErrors()) {
            $allStudents = $api->getResult()->data;

            if (count($allStudents)) {
                foreach ($allStudents as $currentStudent) {

                    /* Find out more about each student
                     */
                    $api = new External_Denbigh2_Api(
                        'Students',
                        array('Student ID' => $currentStudent->{'Student ID'}),
                        array('RFMmax' => 0)
                    );

                    if (!$api->hasErrors()) {
                        $allStudentData = $api->getResult()->data;

                        if (count($allStudentData)) {
                            foreach ($allStudentData as $currentStudentData) {

                                // TODO: No campus for classes?

                                $students[] = array(
                                    'lastname' => $currentStudentData->{'Surname'},
                                    'firstname' => $currentStudentData->{'Preferred Name'},
                                    'yearlevel' => $currentStudentData->{'Academic Year'},
                                    'house' => $currentStudentData->{'House'},
                                    'StudentMedicalAlert' => $currentStudentData->{'Medical Alert Label'},
                                    'StudentSpecialNeedsFlag' => $currentStudentData->{'Special Needs'},
                                    'synergy_id' => $currentStudentData->{'Student ID'},
                                    'ClassCampus' => ''
                                );
                            }
                        }
                    }
                }
            }
        }

        return $students;
    }

    /**
     * Returns a list of *THE IDs OF* teachers that are associated
     * with a class (method is misnamed)
     *
     * @param $classCode
     * @return array
     */
    public function getTeacherName($classCode)
    {
        $ids = array();

        $api = new External_Denbigh2_Api(
            'Classes',
            array('Class' => $classCode),
            array('RFMmax' => 0)
        );

        if (!$api->hasErrors()) {
            $allClasses = $api->getResult()->data;

            if (count($allClasses)) {
                foreach ($allClasses as $currentClass) {
                    if ($currentClass->{'Class'} == $classCode) {
                        $ids[] = $currentClass->{'Teacher Code'};
                    }
                }
            }
        }

        return $ids;
    }
}