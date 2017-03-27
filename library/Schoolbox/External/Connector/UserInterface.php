<?php
/**
 * Interface for external database user
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Tutor;
use Schoolbox\External\Normalise\User\Student;

interface UserInterface
{
    /**
     * Retrieves path on disk to user photo if one exists. If no images exists
     * or the file is not accessible by the server this will return false.
     *
     * @param int|string $externalId The external SIS ID of the user whose photo we want
     * @return false|string
     */
    function getPortrait($externalId);

    /**
     * Retrieve the contact information of one or more student guardians
     *
     * @param int|string|int[]|string[] $externalIds The external SIS IDs of the guardians to retrieve
     * @param bool $hydrateStudents If set, any students linked to the guardian records will also be retrieved and hydrated
     * @return Collection|Guardian
     */
    function getGuardians($externalIds, $hydrateStudents = false);

    /**
     * Retrieve all information required to render the guardian directory, unlike the name suggests this is actually a
     * collection of students with hydrated guardian records
     *
     * @param array $filter
     * @return Collection Collection of hydrated student records
     */
    function getGuardianDirectory($filter = []);

    /**
     * Retrieve one or more student records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS IDs of the students to retrieve
     * @param bool $hydrateGuardians If set, any guardians linked to the student records will also be retrieved and hydrated
     * @return bool|Student|Collection
     */
    function getStudents($externalIds, $hydrateGuardians = false);

    /**
     * Get an array of students whose details should be accessible inside Schoolbox to
     * the specified person
     *
     * @param int|string $externalId This is the external ID of the guardian
     * @return Collection Collection of hydrated student records
     */
    function getStudentsAccessible($externalId);

    /**
     * Retrieve staff records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS ID of the staff member/members to retrieve
     * @return bool|Staff|Collection
     */
    function getStaff($externalIds);

    /**
     * Retrieve all information required to render the staff directory
     *
     * @param array $filter
     * @return Collection
     */
    function getStaffDirectory($filter = []);

    /**
     * Retrieve one or more tutor records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS ID of the tutors
     * @return bool|Tutor|Collection
     */
    function getTutors($externalIds);

    /**
     * Get a list of valid sanitized email addresses for the specified user(s)
     *
     * The returned array is structured as follows
     * [
     *      'Bailey, Sam'        => 'sam.bailey@alaress.com.au',
     *      'Day, Matt'          => 'matt.day@alaress.com.au',
     *      'MacKenzie, Douglas' => 'doug.mackenzie@alaress.com.au'
     * ]
     *
     * @param int|string|int[]|string[] $externalIds Array or string containing the external ID of one or more students
     * @return array An array containing the guardians email addresses
     */
    function getEmails($externalIds);

    /**
     * Retrieves the list of campuses that the specified users are involved in based on their
     * timetabled classes
     *
     * @param int|string|int[]|string[] $externalIds Array or string containing the external ID of one or more users
     * @return Collection
     */
    function getCampusInvolvement($externalIds);
}