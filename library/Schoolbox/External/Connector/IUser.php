<?php

/**
 * Interface for external database user
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector;

use Schoolbox\External\Normalise\User\Common\Campus;
use Schoolbox\External\Normalise\User\Guardian;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Tutor;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

interface IUser
{
    /**
     * Retrieves the Storage\Model that contains a $user's profile picture
     * Returns null if one doesn't exist
     *
     * @param User $user The user instance whose photo we want
     * @return null|Storage\Model the file that represents them, from Storage
     */
    public function getPortrait(User $user): ?Storage\Model;

    /**
     * Retrieve the contact information of one or more student guardians
     *
     * @param int|string|int[]|string[] $externalIds The external SIS IDs of the guardians to retrieve
     * @param bool $hydrateStudents If set, any students linked to the guardian records will also be retrieved and hydrated
     * @return GenericCollection|Guardian[]
     */
    public function getGuardians($externalIds, $hydrateStudents = false);

    /**
     * Retrieve all information required to render the guardian directory, unlike the name suggests this is actually a
     * collection of students with hydrated guardian records
     *
     * @param array $filter
     * @return GenericCollection|Student[]
     */
    public function getGuardianDirectory(array $filter = []);

    /**
     * Retrieve one or more student records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS IDs of the students to retrieve
     * @param bool $hydrateGuardians If set, any guardians linked to the student records will also be retrieved and hydrated
     * @return GenericCollection|Student[]
     */
    public function getStudents($externalIds, $hydrateGuardians = false);

    /**
     * Get an array of students whose details should be accessible inside Schoolbox to
     * the specified person
     *
     * @param int|string $externalId This is the external ID of the guardian
     * @return GenericCollection|Student[]
     */
    public function getStudentsAccessible($externalId);

    /**
     * Retrieve staff records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS ID of the staff member/members to retrieve
     * @return GenericCollection|Staff[]
     */
    public function getStaff($externalIds);

    /**
     * Retrieve all information required to render the staff directory
     *
     * @param array $filter
     * @return GenericCollection|Staff[]
     */
    public function getStaffDirectory(array $filter = []);

    /**
     * Retrieve one or more tutor records from the SIS
     *
     * @param int|string|int[]|string[] $externalIds The external SIS ID of the tutors
     * @return GenericCollection|Tutor[]
     */
    public function getTutors($externalIds);

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
    public function getEmails($externalIds): array;

    /**
     * Retrieves the list of campuses that the specified users are involved in based on their
     * timetabled classes
     *
     * @param int|string|int[]|string[] $externalIds Array or string containing the external ID of one or more users
     * @return GenericCollection|Campus[]
     */
    public function getCampusInvolvement($externalIds);
}
