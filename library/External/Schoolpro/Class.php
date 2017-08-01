<?php
/**
 * External_Schoolpro_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Schoolpro_Class implements External_ClassInterface
{
    use External_MssqlConnector;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setupDatabase();
    }

    /**
     * Get a list of students that are in a particular class
     *
     * @param $classCode
     * @return array|bool
     */
    public function getStudents($classCode)
    {
        $allStudents = [];

        /* Class Codes, when combined, are joined by a comma
         * so split them out and combine request results
         */
        $allClassCodes = explode(',', $classCode);

        foreach ($allClassCodes as $currentClassCode) {
            $vars = [
                'sbGetStudents',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pClassID'  => trim($currentClassCode),
                ],
            ];

            $result = $this->getConnector()->executeStoredProcedureIterator($vars[0], $vars[1]);

            $medicalProc = $this->getConnector()->prepareStoredProcedure('sbGetStudentMedicalConditions');

            foreach ($result as $currentStudent) {
                $medicalNoteStr = '';

                /* Get the medical data for him too
                 */
                $medicalResult = $medicalProc->executeIterator([
                    'pUserName'  => 'alaress',
                    'pUserPass'  => 'alaress',
                    'pSchoolNum' => $currentStudent['ID'],
                ]);

                /* These arrive in a variety of formats
                 */
                foreach ($medicalResult as $currentCondition) {

                    /* Only include medical issues that are Applicable == 1
                     * Just use a basic message to hide the sensitive data,
                     * and bust out of the loop after the first positive result
                     */
                    if ($currentCondition['Applicable']) {
                        $medicalNoteStr = 'This student has medical conditions.';
                        break;
                    }
                }

                $student = [
                    'synergy_id'              => $currentStudent['ID'],
                    'firstname'               => $currentStudent['Firstname'],
                    'lastname'                => $currentStudent['Surname'],
                    'yearlevel '              => $currentStudent['YearLevel'],
                    'house'                   => $currentStudent['House'],
                    'StudentMedicalAlert'     => $medicalNoteStr,
                    'StudentSpecialNeedsFlag' => '',
                    'ClassCampus'             => '',
                ];

                $allStudents[] = $student;
            }
        }
        return $allStudents;
    }

    /**
     * Get the class' teacher's name
     *
     * Wait, what - it returns a list of unique teacher externalIds...?
     *
     * @param $classCode
     * @return array|bool
     */
    public function getTeacherName($classCode)
    {
        $allTeachers = [];
        $allClassCodes = explode(',', $classCode);
        foreach ($allClassCodes as $currentClassCode) {
            $vars = [
                'sbGetTeacherName',
                [
                    'pUserName' => 'alaress',
                    'pUserPass' => 'alaress',
                    'pClassID'  => trim($currentClassCode),
                ],
            ];

            $result = $this->getConnector()->executeStoredProcedureIterator($vars[0], $vars[1]);
            foreach ($result as $currentTeacher) {
                $allTeachers[] = $currentTeacher['ID'];
            }
        }

        return array_unique($allTeachers);
    }
}
