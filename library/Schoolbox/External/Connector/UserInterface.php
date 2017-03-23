<?php
/**
 * Interface for external database user
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright 2017 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Tutor;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\Normalise\
use Schoolbox\User;

interface UserInterface
{
    /**
     * Retrieves user photo if one exists
     *
     * @param int|string $externalId The external SIS ID of the user whose photo we want
     * @return bool|string
     */
    function getPortrait($externalId);

    /**
     * Retrieve the contact information of a students guardian
     *
     * @param int|string $externalId The external SIS ID of the guardian
     * @return bool|Guardian
     */
    function getGuardian($externalId);

    /**
     * Get all the guardian emails for the specified students
     *
     * The returned array is structured as follows
     * [
     *      'Bailey, Sam'        => 'sam.bailey@alaress.com.au',
     *      'Day, Matt'          => 'matt.day@alaress.com.au',
     *      'MacKenzie, Douglas' => 'doug.mackenzie@alaress.com.au'
     * ]
     * 
     * @param int[]|string[] $externalIds Array containing the external ID of one or more students
     * @return array An array containing the guardians email addresses 
     */
    function getGuardianEmails($externalIds);
    
    /**
     * Retrieve a student record from the SIS
     *
     * @param int|string $externalID The external SIS ID of the student
     * @return bool|Student
     */
    function getStudent($externalID);

    /**
     * Retrieve a staff record from the SIS
     *
     * @param int|string $externalId The external SIS ID of the staff
     * @return bool|Staff
     */
    function getStaff($externalId);

    /**
     * Retrieve a tutor record from the SIS
     *
     * @param int|string $externalId The external SIS ID of the tutor
     * @return bool|Tutor
     */
    function getTutor($externalId);

    /**
     * Retrieve all information required to render the student directory
     *
     * The filter array contains any search filters that should be applied to the returned
     * data set. Filter criteria are applied using a logical OR within the criteria, and a
     * logical AND beteween the criteria- for example, the following filter array:
     * 
     *     'YearLevel'         => '7,8,9',
     *     'Name'              => 'Bailey',
     *     'BoardingStatus'    => 'B',
     *     'Form'              => '7B,8A'
     * 
     * ...would translate to the following psuedo-SQL:
     * 
     *     (`YearLevel` = 7 OR `YearLevel` = '8' OR `YearLevel` = '9') AND
     *     (`Name` LIKE '%Bailey%') AND
     *     (`BoardingStatus` = 'B') AND
     *     (`Form` = '7B' OR `Form` = '8A')
     * 
     * @param array $filter
     * @return array
     */
    function getStudentDirectory($filter = []);

    /**
     * Retrieve all information required to render the staff directory
     *
     * @param array $filter
     * @return array
     */
    function getStaffDirectory($filter = []);

    /**
     * Retrieves the current semester from the SIS.
     *
     * This optionally accepts the ID of an external SIS user, and where supported by the SIS,
     * if supplied this will return the semester that this user currently has set as their
     * default. If the SIS does not support this, then it will return the current semester
     * based on the current calendar date.
     *
     * The return value is either a string/int based on the format the external SIS uses
     *
     * @param null|int|string $externalId Optional external user ID
     * @return string|int
     */
    function getCurrentSemester($externalId = null);

    /**
     * Retrieves the current year from the SIS.
     *
     * This optionally accepts the ID of an external SIS user, and where supported by the SIS,
     * if supplied this will return the year that this user currently has set as their
     * default. If the SIS does not support this, then it will return the current year
     * based on the current calendar date.
     *
     * @param null|int|string $externalId Optional external user ID
     * @return int
     */
    function getCurrentYear($externalId = null);

    /**
     * Get a list of subjects for the specified user
     *
     * @param int|string $externalID
     * @return array
     */
    function getSubjectList($externalID);

    /**
     * Get an array of students whose details are accessible to the specified guardian
     *
     * @param int|string $externalID This is the external ID of the guardian
     * @return bool|Student[]
     */
    function getAccessibleStudents($externalID);

    /**
     * Retrieves the default contact email address for the specified user
     *
     * @param $externalId
     * @return null|string
     */
    function getEmail($externalId);

    /**
     * Retrieves the list of campuses that the specified user is involved in based on their
     * timetabled classes
     *
     * @param int|string $externalId
     * @return bool|array
     */
    function getCampusInvolvement($externalId);
    
    /**
     * Determine the boarder type based on a value
     * Returns a human-readable string
     *
     * @param $boarderValue
     *
     * @return string
     */
    function determineBoarderType($boarderValue);
    function getHousemasterByHouseId($houseID);
    function getStudentForms();
}
