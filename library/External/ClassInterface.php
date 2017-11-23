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
     * StudentSpecialNeedsFlag,
     * StudentTutorDescription,
     * synergy_id,
     * ClassCampus
     *
     * @param $classCode
     * @return array|null
     */
    public function getStudents($classCode);

    /**
     * Returns a list of *THE IDs OF* teachers that are associated
     * with a class (method is misnamed)
     *
     * @param $classCode
     * @return array
     */
    public function getTeacherName($classCode);
}
