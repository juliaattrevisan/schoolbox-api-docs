<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Civica\Base\Endpoint\IsValid;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Lookup\Alerts;
use Schoolbox\External\Connector\Civica\Base\Endpoint\Lookup\Caregivers;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\Profile\Model;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


    /**
     * Used to serialize multi-piece data into one string, for UPS purposes usually
     */
    private const SEPARATOR = '~';


    /**
     * A Y-m-d string used to decide if certain data are relevant to that date.
     * Civica gives us a bunch of obsolete data that we need to skip over.
     *
     * @var string $now
     */
    private string $now;


    /**
     * We have to skip over obsolete data - this instance helps us decide which
     * elements are relevant to $now
     *
     * @var IsValid
     */
    private IsValid $isValid;


    /**
     * Profile constructor.
     *
     * @param ValidStrats $validStrats
     * @param LoggerInterface $log
     * @param bool $perform
     * @param IApi $api
     * @param array $watchlist
     * @param Mapper $baseProfileMapper
     * @param UserProfile\Student\Mapper $studentProfileMapper
     * @param UserProfile\Parent\Mapper $parentProfileMapper
     * @param UserProfile\Staff\Mapper $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private array $watchlist,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        /* TODO maybe this should be adf, can't tell atm
         */
        $this->now = date('Y-m-d');
        $this->isValid = new IsValid($this->now);
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Import students' profile data
         * Import their specific parents' profile data
         */
        if ($this->validStrats->isStudentStratEnabled() || $this->validStrats->isFamiliesStratEnabled()) {
            $this->log->info('Processing students and their parents.');

            $this->importStudents();
        }

        /* Import staff profile data
         */
        if ($this->validStrats->isStaffStratEnabled()) {
            $this->log->info('Processing staff.');

            $this->importStaff();
        }
    }


    /**
     * Import students' profile data
     *
     * @return void
     * @throws DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function importStudents(): void
    {
        /* Alerts
         */
        $this->log->info('Downloading student alerts (this might take a while)');

        $studentAlerts = (new Alerts($this->isValid, $this->api))->generate();

        /* Caregivers (parents etc)
         */
        $this->log->info('Downloading student caregivers (this might take a while)');

        $caregivers = (new Caregivers($this->api))->generate();

        /* Continue as intended...now that we downloaded the universe
         */
        $pages = $this->api->retrieveForJurisdiction('GET', 'Students/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $student) {
                $externalId = trim((string)$student['personId']);

                /* Only import data for externalIds in $watchlist, if provided
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                /* FIXME There is an Omega Assumption about using $schools[0]
                 *  because we don't have a means by which to choose which of $schools
                 *  is the one to use...
                 */
                $currentSchool = $student['schools'][0];

                /* This is reused a lot but *only one* of these Enrolment Details is supposed
                 * to be relevant at any given time...supposedly
                 */
                $enrolmentDetails = $this->sortEnrolmentDetails($currentSchool['enrolmentDetails']);

                if (!\count($enrolmentDetails)) {
                    $this->log->warning(sprintf(
                        'Student %s has no enrolment details relevant to %s; skipping',
                        $student['personId'],
                        $this->now
                    ));

                    continue;
                }

                $academicHouse = trim((string)$currentSchool['houseName']);

                /* Someone can be in multiple campuses at the same time
                 * Serialize all those into one big string for now.
                 * SUDS doesn't have true multi-campus support atm
                 */
                $campusName = $this->aggregateStudentCampuses($student['schools']);

                $dob = $this->findDate($student['birthDate']);
                $email = trim((string)$student['email']);
                $firstname = trim((string)$student['firstName']);
                $firstnamePreferred = trim((string)$student['preferredName']);
                $form = '';
                $formTeacher = $this->findFormTeacher();
                $gender = trim((string)$student['gender']);
                $housemaster = $this->findHousemaster($currentSchool);
                $lastname = trim((string)$student['surname']);
                $mobile = trim((string)$student['mobileNumber']);

                /* TODO This might be fulfilled by $student['schools'][0]['isPublishPhoto']
                 *  but there's no documentation for what this element means
                 */
                $photoRedacted = false;

                $studiesCode = trim((string)$student['usi']);
                $title = '';

                /* Sequential Dependency
                 *
                 * Gather some tutor info; group happens to be in the same place as the tutor
                 * itself, so we can collect them both with the same method here
                 */
                $tutor = $this->findTutor($enrolmentDetails);
                $tutorGroup = $tutor['group'];

                $username = '';
                $yearLevel = trim((string)($enrolmentDetails[0]['academicYearName'] ?? null));
                $yearLevelCoordinator = $this->findYearLevelCoordinator();

                /* These will vary based on the campus we chose...
                 */
                $enrolmentDate = $enrolmentDetails[0]['enrolmentDetailStartDate'];
                $leavingDate = $enrolmentDetails[0]['enrolmentDetailEndDate'];

                /* Flags
                 */
                $boardingStatus = (bool)$currentSchool['isBoarder'];
                $esl = false;
                $ib = false;
                $ilp = (bool)$student['isEducationSupport'];

                /* Compile all the alerts for this $externalId
                 *
                 * TODO Not really sure how these are meant to be segregated effectively;
                 *  alert['alertType'] might be a user-defined value inconsistent with
                 *  any other Civica implementation
                 */
                $alerts = [];

                if (isset($studentAlerts[$externalId])) {
                    foreach ($studentAlerts[$externalId] as $studentAlert) {
                        $alerts[] = sprintf(
                            '%s: %s',
                            $studentAlert['alertType'],
                            $studentAlert['alertDescription'],
                        );
                    }
                }

                $comments = '';

                /* FIXME There's only data for user 3964620701842002671 atm
                 *  and they aren't in this data set atm for some reason
                 */
                if (\count($alerts)) {
                    $comments = implode("\n", $alerts);
                }

//                $legal = $this->findLegal();
                $legal = '';
                $medical = $this->findMedicalAlert($currentSchool);
                $messages = '';
                $specialNeeds = (bool)$student['hasLearningDisabilityAlert'];

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($currentSchool);

                /* Dates
                 */
                $enrolment = $this->findDate($enrolmentDate);
                $leaving = $this->findDate($leavingDate);

                /* Arbitrary diagnostics, for lack of a better place to put them
                 */
                $this->log->debug(sprintf(
                    'Found campus(es) "%s" for %s %s (%s)',
                    $campusName,
                    $firstname,
                    $lastname,
                    $externalId
                ));

                /* Parents might inadvertently have the same externalId as someone else's
                 * base profile (e.g. parent has same external id as student)
                 *
                 * This is not usually supposed to happen.
                 * some sis implementations let you have broken data in this way
                 *
                 * Try and load a student profile based on external id. If it exists, just reuse
                 * those pieces. If it doesn't exist, first check if there's an existing base
                 * profile before making a whole new one (this is how you end up with fk issues)
                 */
                try {
                    $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                    $studentBaseProfile = $studentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $studentProfile = $this->studentProfileMapper->createModel();

                    try {
                        $studentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $studentBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $studentProfile->setBaseProfile($studentBaseProfile);
                }

                $studentBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($studentBaseProfile);
                }

                /* Smoosh it all together
                 */
                $studentProfile
                    ->setUsername($username)
                    ->setAlertComment($comments)
                    ->setAlertLegal($legal)
                    ->setAlertMedical($medical)
                    ->setAlertMessage($messages)
                    ->setCampusName($campusName)
                    ->setEmail($email)
                    ->setEnrolmentDate($enrolment)
                    ->setFormName($form)
                    ->setFormTeacherId($formTeacher['id'])
                    ->setFormTeacherName($formTeacher['name'])
                    ->setGender($gender)
                    ->setHeadOfHouseId($housemaster['id'])
                    ->setHeadOfHouseName($housemaster['name'])
                    ->setHouseName($academicHouse)
                    ->setLeavingDate($leaving)
                    ->setMobile($mobile)
                    ->setPhotoRedacted($photoRedacted)
                    ->setStudiesCode($studiesCode)
                    ->setTutorGroup($tutorGroup)
                    ->setTutorTeacherId($tutor['id'])
                    ->setTutorTeacherName($tutor['name'])
                    ->setYearLevel($yearLevel)
                    ->setYearLevelCoordinatorId($yearLevelCoordinator['id'])
                    ->setYearLevelCoordinatorName($yearLevelCoordinator['name'])

                    /**
                     * Flags
                     */
                    ->setBoardingStatus($boardingStatus)
                    ->setEsl($esl)
                    ->setIb($ib)
                    ->setIndividualLearningPlans($ilp)
                    ->setSpecialNeeds($specialNeeds);

                /* Date of birth requires special treatment as ADF doesn't deal with nulls well
                 */
                if ($dob) {
                    $studentProfile
                        ->setDateOfBirth(new Alaress_Date_Format($dob, 'Ymd'));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (\count($emergencyContacts)) {
                    if (isset($emergencyContacts[0])) {
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContacts[0]['name'])
                            ->setEmergencyContactPhone1($emergencyContacts[0]['phone'])
                            ->setEmergencyContactPhoneAlt1($emergencyContacts[0]['phone_alt']);
                    };

                    if (isset($emergencyContacts[1])) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContacts[1]['name'])
                            ->setEmergencyContactPhone2($emergencyContacts[1]['phone'])
                            ->setEmergencyContactPhoneAlt2($emergencyContacts[1]['phone_alt']);
                    }

                    if (isset($emergencyContacts[2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts[2]['name'])
                            ->setEmergencyContactPhone3($emergencyContacts[2]['phone'])
                            ->setEmergencyContactPhoneAlt3($emergencyContacts[2]['phone_alt']);
                    }
                } else {
                    /* This student doesn't have any emergency contacts anymore
                     * Clear whatever was there
                     */
                    $studentProfile
                        ->setEmergencyContactName1(null)
                        ->setEmergencyContactPhone1(null)
                        ->setEmergencyContactPhoneAlt1(null)
                        ->setEmergencyContactName2(null)
                        ->setEmergencyContactPhone2(null)
                        ->setEmergencyContactPhoneAlt2(null)
                        ->setEmergencyContactName3(null)
                        ->setEmergencyContactPhone3(null)
                        ->setEmergencyContactPhoneAlt3(null);
                }

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding student profile for %s (%s)',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->studentProfileMapper->save($studentProfile);
                }

                /* Add their parents' profile data
                 */
                $this->importParentsOfStudent($externalId, $studentBaseProfile, $caregivers);
            }
        }
    }


    /**
     * Find the most recent Campus someone was in; if there aren't any enrolment
     * details, return null I guess, and let UPS sort them out
     *
     * @param array $enrolmentDetails
     * @return array
     */
    private function sortEnrolmentDetails(array $enrolmentDetails): array
    {
        $stack = [];

        /* No campus? No trabajo
         */
        if (!\count($enrolmentDetails)) {
            return $stack;
        }

        /* Pre-sort the list
         */
        foreach ($enrolmentDetails as $enrolmentDetail) {
            if ($this->isValid->isSatisifiedBy($enrolmentDetail, 'enrolmentDetailStartDate', 'enrolmentDetailEndDate')) {
                $stack[] = $enrolmentDetail;
            }
        }

        return $stack;
    }


    /**
     * Civica "schools" correspond to our idea of "campuses"
     * (schoolbox campuses are a way of categorising the whole demographic of humans)
     *
     * A person's set of campus is derived from all the $school elements that have at
     * least one relevant $enrolmentDetail...but there was supposed to be an official
     * way to conclusively know
     *
     * Serialize multiple schools/campuses into one string to decipher in UPS
     *
     * SUDS doesn't support true multi-school support, really - we just have one
     * field which we h4x0r to "make work" in UPS.
     *
     * @param array $schools
     * @return string
     */
    private function aggregateStudentCampuses(array $schools): string
    {
        $stack = [];

        foreach ($schools as $school) {
            /* TODO There is *supposed* to be a way to decide which ones
             *  are relevant to a particular human, but it's not obvious
             *  Each school also has its own $enrolmentDetails for which
             *  zero or one $enrolmentDetail is relevant, by date, maybe
             */
            $enrolmentDetails = $this->sortEnrolmentDetails($school['enrolmentDetails']);

            if (!\count($enrolmentDetails)) {
                /* No enrolment details relevant for $school
                 * Nothing to shank from here. Keep going
                 */
                continue;
            }

            /* We found at least one thing that's relevant
             * Scoop up all the unique $campusNames
             */
            foreach ($enrolmentDetails as $enrolmentDetail) {
                $campusName = trim((string)$enrolmentDetail['campusName']);

                $stack[$campusName] = $campusName;
            }
        }

        /* "Erin says worm"
         *
         * Serialize it with the separator she chose; you'll have to work
         * with this in UPS
         */
        return implode(self::SEPARATOR, array_values($stack));
    }


    /**
     * Convert a date from possibly null/empty value
     *
     * @param string|null $date
     * @return Alaress_Date_Format|null
     */
    public function findDate(?string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param array $school
     * @return array
     */
    public function findEmergencyContacts(array $school): array
    {
        $contacts = [];
        $n = 0;

        /* Sort by Emergency Ranking, apparently
         */
        $emergencyContacts = $school['emergencyContacts'];

        uasort($emergencyContacts, static function ($a, $b) {
            if ((int)$a['emergencyRanking'] > (int)$b['emergencyRanking']) {
                return 1;
            }

            return 0;
        });

        foreach ($emergencyContacts as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                $fullname = trim((string)$emergencyContact['contactName']);
                $relation = trim((string)$emergencyContact['relationship']);
                $mobile = trim((string)$emergencyContact['mobileNumber']);
                $phone = trim((string)$emergencyContact['phoneNumber']);

                /* Suffix the $relation if provided
                 */
                if ('' !== $relation) {
                    $fullname .= " ({$relation})";
                }

                /* Aggregate them if we have a name
                 */
                if ('' !== $fullname) {
                    $contacts[] = [
                        'name' => $fullname,
                        'phone' => $mobile,
                        'phone_alt' => $phone
                    ];
                }
            }

            $n++;
        }

        return $contacts;
    }


    /**
     * Find a form teacher from their form code
     *
     * @return array
     */
    private function findFormTeacher(): array
    {
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a housemaster from their house name
     *
     * @param array $school
     * @return array
     */
    private function findHousemaster(array $school): array
    {
        return [
            'id' => trim((string)$school['houseStaffId']),
            'name' => trim((string)$school['houseStaffName'])
        ];
    }


//    /**
//     * Find legal alerts
//     *
//     * @return string|null
//     */
//    private function findLegal(): ?string
//    {
//        /* TODO Fill in these legal alert vars
//         */
//        return null;
//    }


    /**
     * Find medical data for student
     *
     * @param array $school
     * @return string|null
     */
    private function findMedicalAlert(array $school): ?string
    {
        if ($school['medicalAlerts'] && is_iterable($school['medicalAlerts'])) {
            $medicalAlerts = [];

            /* Collate them all (arguably this is an unordered list, but ok)
             */
            foreach ($school['medicalAlerts'] as $medicalAlert) {
                $medicalAlerts[] = sprintf("\n\n%s", trim((string)$medicalAlert));
            }

            /* Crush them all down ito a single string
             */
            return implode('', $medicalAlerts);
        }

        return null;
    }


    /**
     * Find a tutor from their id
     *
     * @param array $enrolmentDetails
     * @return array
     */
    private function findTutor(array $enrolmentDetails): array
    {
        /* No campuses? No trabajo
         */
        if (!\count($enrolmentDetails)) {
            return [
                'id' => null,
                'name' => null,
                'group' => null,
            ];
        }

        $campus = $enrolmentDetails[0];

        return [
            'id' => trim((string)$campus['rollGroupStaffId']),
            'name' => trim((string)$campus['rollGroupStaffName']),
            'group' => trim((string)$campus['rollGroupCode'])
        ];
    }


    /**
     * Find a year level coordinator from their year level
     *
     * @return array
     */
    private function findYearLevelCoordinator(): array
    {
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Import parent profile data for a given student
     *
     * @param string $studentExternalId
     * @param Model $studentBaseProfile
     * @param array $caregivers
     * @throws Exception
     * @throws DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(
        string $studentExternalId,
        UserProfile\Model $studentBaseProfile,
        array $caregivers
    ): void {
        $parents = $caregivers[$studentExternalId] ?? [];

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {
            $externalId = trim((string)$parent['caregiverPersonId']);
            $email = trim((string)$parent['email']);
            $firstname = trim((string)$parent['caregiverFirstName']);
            $firstnamePreferred = trim((string)$parent['caregiverPreferredName']);
            $lastname = trim((string)$parent['caregiverSurname']);
            $phoneHome = trim((string)$parent['homePhoneNumber']);
            $phoneMobile = trim((string)$parent['mobileNumber']);
            $phoneWork = trim((string)$parent['businessPhoneNumber']);

            /* I guess this is a tuple, really
             */
            $relationship = trim((string)$parent['caregiverStudentRelationship']);
            $relationshipType = trim((string)$parent['caregiverType']);

            if ('' !== $relationshipType) {
                $relationship .=  " ({$relationshipType})";
            }

            $title = trim((string)$parent['caregiverTitle']);
            $username = '';

            /* Decide if this parent record should have a relationship with this student record
             * hasAccessRestriction true means they -shouldn't- and we can skip their processing
             */
            if ($parent['hasAccessRestriction']) {
                $this->log->debug(sprintf(
                    '%s %s has restricted access: skipping',
                    $firstname,
                    $lastname
                ));

                continue;
            }

            /* Parents might inadvertently have the same externalId as someone else's
             * base profile (e.g. parent has same external id as student)
             *
             * This is not usually supposed to happen.
             * some sis implementations let you have broken data in this way
             *
             * Try and load a parent profile based on external id. If it exists, just reuse
             * those pieces. If it doesn't exist, first check if there's an existing base
             * profile before making a whole new one (this is how you end up with fk issues)
             */
            try {
                $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
                $parentBaseProfile = $parentProfile->getBaseProfile();
            } catch (Exception $e) {
                $parentProfile = $this->parentProfileMapper->createModel();

                try {
                    $parentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                } catch (Exception $e) {
                    $parentBaseProfile = $this->baseProfileMapper->createModel();
                }

                $parentProfile->setBaseProfile($parentBaseProfile);
            }

            $parentBaseProfile
                ->setExternalId($externalId)
                ->setTitle($title)
                ->setFirstname($firstname)
                ->setFirstnamePreferred($firstnamePreferred)
                ->setLastname($lastname);

            /* Then save it
             */
            if ($this->perform) {
                $this->baseProfileMapper->save($parentBaseProfile);
            }

            $parentProfile
                ->setPhoneHome($phoneHome)
                ->setPhoneWork($phoneWork)
                ->setMobile($phoneMobile)
                ->setEmail($email)
                ->setUsername($username);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding parent profile for %s (%s)',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->parentProfileMapper->save($parentProfile);
            }

            /* Manufacture relations
             */

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding relation between %s, the %s of %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                trim($relationship),
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $relationship = $this->relationshipMapper->createModel()
                    ->setUserProfile($parentBaseProfile)
                    ->setChildProfile($studentBaseProfile)
                    ->setType($relationship);

                $this->relationshipMapper->save($relationship);
            }
        }
    }


    /**
     * Import staff profile data
     *
     * @return void
     * @throws DefinitionException
     * @throws Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function importStaff(): void
    {
        /* Continue as intended...now that we downloaded the universe
         */
        $pages = $this->api->retrieveForJurisdiction('GET', 'Staff/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $staff) {
                $externalId = trim((string)$staff['personId']);

                /* Only import data for externalIds in $watchlist, if provided
                 */
                if (!(new Watchlist())->isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                /* FIXME There is an Omega Assumption about using $schools[0]
                 *  because we don't have a means by which to choose which of $schools
                 *  is the one to use...
                 */
                $currentSchool = $staff['schools'][0];

                $firstname = trim((string)$staff['firstName']);
                $firstnamePreferred = trim((string)$staff['preferredFirstName']);
                $lastname = trim((string)$staff['surname']);
                $building = '';
                $campusName = $this->aggregateStaffCampuses($staff['schools']);
                $category = '';
                $department = '';
                $email = trim((string)$staff['workEmail']);
                $house = '';
                $phone = trim((string)$staff['workPhoneNumber']);
                $positionTitle = $this->findPositions($currentSchool['positions']);
                $room = '';
                $title = trim((string)$staff['title']);
                $username = '';

                /* Arbitrary diagnostics, for lack of a better place to put them
                 */
                $this->log->debug(sprintf(
                    'Found campus(es) "%s" for %s %s (%s)',
                    $campusName,
                    $firstname,
                    $lastname,
                    $externalId
                ));

                /* Find out if this externalId already has a staff profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
                    $staffBaseProfile = $staffProfile->getBaseProfile();
                } catch (Exception $e) {
                    $staffProfile = $this->staffProfileMapper->createModel();

                    try {
                        $staffBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
                    } catch (Exception $e) {
                        $staffBaseProfile = $this->baseProfileMapper->createModel();
                    }

                    $staffProfile->setBaseProfile($staffBaseProfile);
                }

                $staffBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setFirstnamePreferred($firstnamePreferred)
                    ->setLastname($lastname);

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($staffBaseProfile);
                }

                $staffProfile
                    ->setBuilding($building)
                    ->setCampusName($campusName)
                    ->setCategory($category)
                    ->setDepartment($department)
                    ->setEmail($email)
                    ->setHouse($house)
                    ->setPhone($phone)
                    ->setPositionTitle($positionTitle)
                    ->setRoom($room)
                    ->setUsername($username);

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding staff profile for %s (%s)',
                    trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}"),
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->staffProfileMapper->save($staffProfile);
                }
            }
        }
    }


    /**
     * Aggregates all the position titles this staff member owns
     * and presents it as a csv
     *
     * @param array $positions
     * @return string
     */
    private function findPositions(array $positions): string
    {
        $stack =  [];

        foreach ($positions as &$position) {
            $position['startDate'] = trim((string)$position['startDate']);
            $position['endDate'] = trim((string)$position['endDate']);

            /* Use the dates to figure out if this is relevant or not
             */
            if ($this->isValid->isSatisifiedBy($position)) {
                $stack[$position['position']] = $position['position'];
            }
        }

        unset($position);

        return implode(', ', array_values($stack));
    }


    /**
     * Civica "schools" correspond to our idea of "campuses"
     * (schoolbox campuses are a way of categorising the whole demographic of humans)
     *
     * A person's set of campus is derived from all the $school elements that have at
     * least one relevant $position['isPrimaryAssignment'] = 1...anything else is
     * skipped, I guess, for lack of any way to know a campus. This sounds weird to me
     * but whatever - Civica really has some weird ways of doing things
     *
     * Serialize multiple schools/campuses into one string to decipher in UPS
     *
     * SUDS doesn't support true multi-school support, really - we just have one
     * field which we h4x0r to "make work" in UPS.
     *
     * @param array $schools
     * @return string
     */
    private function aggregateStaffCampuses(array $schools): string
    {
        $stack = [];

        foreach ($schools as $school) {
            foreach ($school['positions'] as &$position) {
                $position['startDate'] = trim((string)$position['startDate']);
                $position['endDate'] = trim((string)$position['endDate']);

                /* Use the dates to figure out if this is relevant or not;
                 * but also this random isPrimaryAssignment field...it seems
                 * the combination of these data decides campus relevancy
                 * because Civica reasons
                 */
                if ($position['isPrimaryAssignment'] && $this->isValid->isSatisifiedBy($position)) {
                    $stack[$position['campus']] = $position['campus'];
                }
            }
        }

        unset($position);

        /* "Erin says worm"
         *
         * Serialize it with the separator she chose; you'll have to work
         * with this in UPS
         */
        return implode(self::SEPARATOR, array_values($stack));
    }
}
