<?php
/**
 * External_Schoolpro_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * @inheritdoc
     */
    public function getStudents(string $classCode): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

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

            foreach ($this->getConnector()->getStoredProcedureIterator($vars[0], $vars[1]) as $currentStudent) {
                $student = [
                    'synergy_id'              => $currentStudent['ID'],
                    'firstname'               => $currentStudent['Firstname'],
                    'lastname'                => $currentStudent['Surname'],
                    'yearlevel '              => $currentStudent['YearLevel'],
                    'house'                   => $currentStudent['House'],
                    'StudentMedicalAlert'     => '',
                    'StudentSpecialNeedsFlag' => '',
                    'EnglishSecondFlag'       => '',
                    'StudentTutorDescription' => '',
                ];

                $allStudents[] = $student;
            }
        }

        // Move medical result to outside the result iteration
        // Otherwise second iterator clobbers the first
        foreach ($allStudents as &$student) {

            /* Get the medical data for him too
             */
            $medicalResult = $this->getConnector()->getStoredProcedureIterator(
                'sbGetStudentMedicalConditions',
                [
                    'pUserName'  => 'alaress',
                    'pUserPass'  => 'alaress',
                    'pSchoolNum' => $student['synergy_id'],
                ]
            );

            /* These arrive in a variety of formats
             */
            foreach ($medicalResult as $currentCondition) {

                /* Only include medical issues that are Applicable == 1
                 * Just use a basic message to hide the sensitive data,
                 * and bust out of the loop after the first positive result
                 */
                if ($currentCondition['Applicable']) {
                    $student['StudentMedicalAlert'] = 'This student has medical conditions.';
                    break;
                }
            }
        }
        unset($student);

        return $allStudents;
    }

    /**
     * @inheritdoc
     */
    public function getTeachers(string $classCode, Alaress_Date_Format $date = null): array
    {
        if (!$classCode) {
            /* Dont bother if you got no code */
            return [];
        }

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

            $result = $this->getConnector()->getStoredProcedureIterator($vars[0], $vars[1]);
            foreach ($result as $currentTeacher) {
                $allTeachers[] = $currentTeacher['ID'];
            }
        }

        return array_unique($allTeachers);
    }

    /**
     * @return array
     */
    public function findClassTypes(): array
    {
        return [];
    }

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array
    {
        return [];
    }
}
