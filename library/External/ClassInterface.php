<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */

interface External_ClassInterface {

    /**
     * Get a list of students in a particular $classCode
     *
     * lastname,
     * firstname,
     * yearlevel,
     * house,
     * StudentMedicalAlert,
     * EnglishSecondFlag: string. '0' => false, '1' => true, '' => unknown
     * StudentSpecialNeedsFlag,
     * StudentTutorDescription,
     * synergy_id,
     * ClassCampus
     *
     * @param string $classCode  CSV list of class codes to find students in. eg 'ABC123' or 'ABC123,XYZ456'
     * @return string[][]
     */
    public function getStudents(string $classCode): array;

    /**
     * Returns a list of *THE EXTERNAL IDs* of teachers that are associated
     * with a class (method is misnamed)
     *
     * @param string $classCode  CSV list of class codes to find students in. eg 'ABC123' or 'ABC123,XYZ456'
     * @return string[]
     */
    public function getTeacherName(string $classCode): array;
}
