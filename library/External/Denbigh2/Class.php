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
        // TODO: Implement getStudents() method.
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

        $api = new External_Denbigh2_Api('Classes', array(
            'Class' => $classCode
        ));

        if (!count($api->getErrors())) {
            $allClasses = $api->getResult()->data;

            foreach ($allClasses as $currentClass) {
                $ids[] = $currentClass->{'Teacher Code'};
            }
        }

        return $ids;
    }
}