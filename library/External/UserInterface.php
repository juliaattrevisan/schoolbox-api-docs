<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:45 PM
 * To change this template use File | Settings | File Templates.
 */

interface External_UserInterface {
    public function getParent($externalUserId);

    public function getParentSync($externalUserId);

    public function getParentEmails($externalUserId);

    public function getStudent($externalUserId);

    public function getStaff($externalUserId);

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form);

    public function getStudentContactList($externalUserId);

    public function getCurrentSemester();

    public function getSubjectList($externalUserId, $userId);

    public function getEnrolledStudents($externalUserId);

    public function getEmail($externalUserId);

    public function getPortrait($externalUserId);

    public function getStudentUserAccountInfo();

    public function getParentUserAccountInfo();

    public function getStaffUserAccountInfo();

    public function determineBoarderType($boarderValue);

    public function getHousemasterByHouseId($houseID);
}
