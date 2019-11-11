<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */

interface External_ClassInterface extends External\IConnector
{

    /**
     * Get a list of students in a particular $classCode
     *
     * string      $return[]['lastname']
     * string      $return[]['firstname']
     * string      $return[]['yearlevel']
     * null|string $return[]['house']                     The name of the student's house. NULL if unknown.
     * string      $return[]['StudentMedicalAlert']
     * string      $return[]['EnglishSecondFlag']         '0' => false, '1' => true, '' => unknown
     * string      $return[]['StudentSpecialNeedsFlag']
     * string      $return[]['StudentTutorDescription']
     * string      $return[]['synergy_id']
     *
     * @param string $classCode  CSV list of class codes to find students in. eg 'ABC123' or 'ABC123,XYZ456'
     * @return string[][]
     */
    public function getStudents(string $classCode): array;

    /**
     * Returns a list of *THE EXTERNAL IDs* of teachers that are associated
     * with a class
     *
     * @param string $classCode  CSV list of class codes to find students in. eg 'ABC123' or 'ABC123,XYZ456'
     * @return string[]
     */
    public function getTeachers(string $classCode): array;

    /**
     * @return array
     */
    public function findClassTypes(): array;

    /**
     * @param string $filetype
     * @param array  $timetableCode
     *
     * @return array
     */
    public function findClassesByType(string $filetype, array $timetableCode): array;
}
