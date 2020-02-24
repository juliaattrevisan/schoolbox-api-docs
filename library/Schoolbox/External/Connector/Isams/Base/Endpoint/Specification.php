<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Specification implements ISpecification
{

    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * Specification constructor.
     *
     * @param Batchxml $batchxml
     */
    public function __construct(Batchxml $batchxml)
    {
        $this->batchxml = $batchxml;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */

            /* There is at least one current student
             *
             * SchoolId is their externalId
             * SchoolCode is their username
             * Id is an isams internal id which joins elsewhere in isams' db
             */
            '/iSAMS/PupilManager/CurrentPupils/Pupil',
            '/iSAMS/PupilManager/CurrentPupils/Pupil[@Id]',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/SchoolCode',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/SchoolId',

            /* Profile info
             */
            '/iSAMS/PupilManager/CurrentPupils/Pupil/AcademicHouse',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/DOB',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/EmailAddress',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/EnrolmentDate',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Form',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Gender',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/MobileNumber',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/NCYear',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Preferredname',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Surname',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Title',
            '/iSAMS/PupilManager/CurrentPupils/Pupil/Tutor',

            /* There is at least one academic house with a name
             */
            '/iSAMS/SchoolManager/AcademicHouses/House',
            '/iSAMS/SchoolManager/AcademicHouses/House/Name',

            /* There is at least one school division
             * These map to campuses via year whose id can be found as an attribute of a node
             */
            '/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision',
            '/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision/Code',
            '/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision/Name',
            '/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision/Years/Year',
            '/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision/Years/Year[@YearId]',

            /* There is at least one identifiable form with a name
             */
            '/iSAMS/SchoolManager/Forms/Form',
            '/iSAMS/SchoolManager/Forms/Form[@Id]',

            /* There is at least one medical record
             */
            '/iSAMS/PupilManager/CurrentPupilsHealth/Pupil',
            '/iSAMS/PupilManager/CurrentPupilsHealth/Pupil[@Id]',

            /* There is at least one special education registry record for an identifiable student
             */
            '/iSAMS/SENManager/SEN/Register',
            '/iSAMS/SENManager/SEN/Register[@PupilId]',
            '/iSAMS/SENManager/SEN/Register/RegisterFlag',

            /* There is at least one Comments record
             */
            '/iSAMS/SENManager/Requirements/Type',
            '/iSAMS/SENManager/Requirements/Type[@PupilId]',

            /**
             * Parent Profile nodes
             */

            /* There is at least one identifiable parent with an identifiable student
             * Parents have a list of associated students in their node
             *
             * EmailAddress is the parent's username
             */
            '/iSAMS/PupilManager/Contacts/Contact[@Id]',
            '/iSAMS/PupilManager/Contacts/Contact/EmailAddress',
            '/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil',
            '/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil[@Id]',

            /* Profile info
             */
            '/iSAMS/PupilManager/Contacts/Contact/Forename',
            '/iSAMS/PupilManager/Contacts/Contact/Mobile',
            '/iSAMS/PupilManager/Contacts/Contact/RelationshipRaw',
            '/iSAMS/PupilManager/Contacts/Contact/SOS',
            '/iSAMS/PupilManager/Contacts/Contact/Surname',
            '/iSAMS/PupilManager/Contacts/Contact/Telephone',
            '/iSAMS/PupilManager/Contacts/Contact/Title',

            /**
             * Staff Profile nodes
             */

            /* There is at least one staff member
             * They teach classes and are housemasters of academic houses
             *
             * PersonId is their externalId
             * UserName is their username
             * UserCode isams internal id which joins elsewhere in isams' db
             */
            '/iSAMS/HRManager/CurrentStaff/StaffMember',
            '/iSAMS/HRManager/CurrentStaff/StaffMember[@PersonId]',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/UserCode',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/UserName',

            /* Profile info
             */
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Divisions',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Divisions/Division',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Divisions/Division/Name',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/FullName',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/PreferredName',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Roles',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Roles/Role',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Roles/Role/Name',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/SchoolEmailAddress',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/SchoolMobileNumber',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Surname',
            '/iSAMS/HRManager/CurrentStaff/StaffMember/Title',

            /**
             * Timetable nodes
             */

            /* There is at least one identifiable published timetable
             * Published Timetables should have at least one Schedule
             * Each Schedule knows about a Teacher and the subject Code that's occurring
             */
            '/iSAMS/TimetableManager/PublishedTimetables/Timetable',
            '/iSAMS/TimetableManager/PublishedTimetables/Timetable[@Id]',
            '/iSAMS/TimetableManager/PublishedTimetables/Timetable/Schedules',
            '/iSAMS/TimetableManager/PublishedTimetables/Timetable/Schedules/Schedule/Teacher',
            '/iSAMS/TimetableManager/PublishedTimetables/Timetable/Schedules/Schedule/Code',

            /* There is at least one identifiable week structure
             * This corresponds to our Timetable Definition concept
             * It should contain a list of Days, which should have Periods
             */
            '/iSAMS/TimetableManager/Structure/Week',
            '/iSAMS/TimetableManager/Structure/Week[@Id]',
            '/iSAMS/TimetableManager/Structure/Week/Days/Day',
            '/iSAMS/TimetableManager/Structure/Week/Days/Day/Periods/Period',
            '/iSAMS/TimetableManager/Structure/Week/Days/Day/Periods/Period[@Id]',

            /* There is at least one Set
             * This is a container of Subject and Teacher and occurs somewhere in a Schedule
             * This is closest to our concept of "a subject that is being taught"
             */
            '/iSAMS/TeachingManager/Sets/Set',
            '/iSAMS/TeachingManager/Sets/Set/SetCode',

            /* There is at least one SetList
             * This is a list of students participating in a Set
             */
            '/iSAMS/TeachingManager/SetLists/SetList',
            '/iSAMS/TeachingManager/SetLists/SetList/SetId',

            /* There is at least one identifiable building
             * Subjects are taught in these locations
             */
            '/iSAMS/EstateManager/Buildings/Building/Classrooms/Classroom',
            '/iSAMS/EstateManager/Buildings/Building/Classrooms/Classroom[@Id]',
        ];

        $endpoints = [];

        foreach ($paths as $path) {
            if ($this->batchxml->fetchXml($path)) {
                $endpoints[] = [
                    'message' => $path,
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $path,
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        /* Find the timetable identifier, for reference, in the status screen
         */

        /* Get a list of all the timetables to process
         */
        $timetables = $this->batchxml->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $endpoints[] = [
                'message' => 'No PublishedTimetables detected.',
                'status' => ISpecification::STATUS_MISSING
            ];
        }

        foreach ($timetables as $timetable) {
            $timetableMessage = sprintf(
                'Found timetable %s: assign %s to ext_timetable_code',
                trim((string)$timetable->Name),
                trim((string)$timetable->xpath('@Id')[0]->Id)
            );

            $endpoints[] = [
                'message' => $timetableMessage,
                'status' => ISpecification::STATUS_OK
            ];
        }

        return $endpoints;
    }
}
