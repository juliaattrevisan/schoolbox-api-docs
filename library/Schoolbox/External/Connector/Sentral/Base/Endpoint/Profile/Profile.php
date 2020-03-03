<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile;

use Alaress_Date_Format;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup\EmergencyContacts;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup\Enrolment;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup\LearningPlans;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup\Medical;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile\Lookup\Phone;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use UnexpectedValueException;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{

    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;


    /**
     * Sometimes the mapper doesn't save correctly; this is the number of times
     * it should retry before skipping entirely
     */
    private const MAX_RETRY = 5;


    /**
     * Number of seconds to sleep between retries
     */
    private const RETRY_SLEEP = 5;


    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 1000;


    /**
     * Employment status for departed staff: if a staff member is departed, they
     * should not be synced.
     */
    private const STAFF_EMPLOYMENT_STATUS_DEPARTED = 'left';


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Api
     */
    private $api;


    /**
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * Profile constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param Api                             $api
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
    }


    /**
     * Import user profile data into sis_user_profile*
     */
    public function import(): void
    {
        /* Purge old relations before re-inserting them
         */
        if ($this->perform) {
            $this->relationshipMapper->truncate();
        }

        /* Import students' profile data
         * Import their specific parents' profile data
         */
        $this->log->info('Processing students and their parents.');

        $this->importStudents();

        /* Import staff profile data
         */
        $this->log->info('Processing staff.');

        $this->importStaff();

        /* Remove any orphaned profiles
         */
        if ($this->perform) {
            $orphans = (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))->remove();

            $this->log->info("Removed {$orphans} orphaned profiles.");
        }
    }


    /**
     * Import students' profile data
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importStudents(): void
    {

        /* Compile lookup records
         * If there's too much data here we can access each of these urls directly, per record,
         * instead of trying to build a lookup. That's a lot of api calls though...
         */
        $this->log->info('Fetching sideloads as lookups.');

        $lookups['medical'] = (new Medical($this->api))->compile()->getRecords();
        $lookups['emergencyContacts'] = (new EmergencyContacts($this->api))->compile()->getRecords();
        $lookups['learningPlans'] = (new LearningPlans($this->api))->compile()->getRecords();
        $lookups['enrolment'] = (new Enrolment($this->api))->compile()->getRecords();
        $lookups['phone'] = (new Phone($this->api))->compile()->getRecords();

        $this->log->info(sprintf(
            'Fetched lookups: %s',
            implode(', ', array_keys($lookups))
        ));

        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = 'enrolments/student-contact?include=person,student,studentPerson&limit=200';

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            /* Compile all the pieces
             */
            $relationshipIterator = new RelationshipIterator($payload, $lookups);

            foreach ($relationshipIterator as $relation) {
                $externalId = (string)$relation['student']['externalId'];

                /* Didn't find this, so I guess it's externalId for now
                 * Otherwise set it from UPS
                 */
                $username = (string)$relation['student']['externalId'];

                $title = $relation['student']['title'];
                $firstname = $relation['student']['firstname'];
                $lastname = $relation['student']['lastname'];

                /* Derived from year level, it seems
                 * Set it in UPS
                 */
                $campus = '';

                $dob = $relation['student']['dob'];
                $email = $relation['student']['email'];
                $enrolmentDate = $relation['student']['enrolment']['startDate'];
                $form = '';
                $gender = $relation['student']['gender'];
                $academicHouse = '';
                $leavingDate = $relation['student']['enrolment']['endDate'];
                $legal = '';
                $mobile = $relation['student']['phone']['mobile'] ?? null;
                $studiesCode = '';

                // rollclass, maybe?
                $tutor = '';
                $tutorGroup = '';

                $yearLevel = $relation['student']['enrolment']['year'];

                /* Flags
                 */
                $boardingStatus = (bool)$relation['student']['enrolment']['isBoarding'];
                $esl = false;
                $ib = false;
                $ilp = false;
                $medicalAlert = $relation['student']['medical'];
                $messages = '';
                $specialNeeds = false;
                $comments = '';

                /* Disregard inactive students
                 */
                if (!$relation['student']['active']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for student %s - student is inactive',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Disregard inactive students
                 */
                if ('' === $externalId) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for student %s - no externalId detected',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Students without usernames are skipped
                 */
                if ('' === $username) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for student %s - no username detected',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Find out if this externalId already has a student profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
                    $studentBaseProfile = $studentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $studentProfile = $this->studentProfileMapper->createModel();
                    $studentBaseProfile = $this->baseProfileMapper->createModel();

                    $studentProfile->setBaseProfile($studentBaseProfile);
                }

                $studentBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setLastname($lastname);

                /* Log it
                 */
                $this->log->debug(sprintf(
                    'Preparing base profile for student %s (%s)',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $result = $this->baseProfileMapper->save($studentBaseProfile);
                }

                $this->log->debug(sprintf(
                    'Added base profile for student %s (%s) with sis_user_profile_id %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId,
                    $studentBaseProfile->getId()
                ));

                /* Fish out alert comments
                 */
                $alertComments = $this->findComments();

                /* Fish out legal alerts
                 */
                $alertLegal = $this->findLegal();

                /* Fish out medical alerts
                 */
                $alertMedical = $this->findMedicalAlert($medicalAlert);

                /* Fish out campus name
                 */
                $campusName = $this->findCampusName();

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($relation['student']['emergencyContacts']);

                /* Find out a staff member via their user code
                 */
                $formTeacher = $this->findFormTeacher();

                /* Fish out a staff member via their user code
                 */
                $housemaster = $this->findHousemaster();

                /* Fish out special needs
                 */
                $specialNeeds = $this->findSpecialNeeds();

                /* Fish out a staff member via their user code
                 */
                $tutor = $this->findTutor();

                /* Find out a staff member via their user code
                 */
                $yearLevelCoordinator = $this->findYearLevelCoordinator();

                /* Dates
                 */
                $enrolment = $this->findDate($enrolmentDate);
                $leaving = $this->findDate($leavingDate);

                /* Smoosh it all together
                 */
                $studentProfile
                    ->setUsername($username)
                    ->setAlertComment($alertComments)
                    ->setAlertLegal($alertLegal)
                    ->setAlertMedical($alertMedical)
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
                        ->setDateOfBirth($this->findDate($dob));
                }

                /**
                 * Emergency Contacts
                 * ...because we didn't normalise it, I'm hardcoding these
                 */
                if (\count($emergencyContacts)) {
                    if (isset($emergencyContacts[0])) {
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContacts[0]['name'])
                            ->setEmergencyContactPhone1($emergencyContacts[0]['phone']);
                    }

                    if (isset($emergencyContacts[1])) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContacts[1]['name'])
                            ->setEmergencyContactPhone2($emergencyContacts[1]['phone']);
                    }

                    if (isset($emergencyContacts[2])) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts[2]['name'])
                            ->setEmergencyContactPhone3($emergencyContacts[2]['phone']);
                    }
                }

                /* Log it
                 */
                $this->log->debug(sprintf(
                    'Preparing roletype-specific profile for student %s (%s) with sis_user_profile_id %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId,
                    $studentBaseProfile->getId()
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->studentProfileMapper->save($studentProfile);
                }

                $this->log->info(sprintf(
                    'Added roletype-specific profile for student %s (%s) with sis_user_profile_id %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                    $externalId,
                    $studentBaseProfile->getId()
                ));

                /* Add their parents' profile data
                 */
                $this->importParentsOfStudent($relation, $studentBaseProfile);
            }

            /* Prepare for next page if it exists
             */
            $nextUrl =  $payload['links']['next'] ?? null;
        }
    }


    /**
     * Find campus name
     *
     * Assign campuses if they exist in the mapping
     * Otherwise quietly disregard bad data
     *
     * @return string|null
     */
    private function findCampusName(): ?string
    {
        return null;
    }


    /**
     * Find comments info for a given student
     *
     * @return string
     */
    private function findComments(): string
    {
        return '';
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
     * @param array|null $emergencyContacts
     * @return array
     */
    public function findEmergencyContacts(?array $emergencyContacts): array
    {
        $contacts = [];
        $n = 0;

        foreach ($emergencyContacts as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                $fullname = $emergencyContact['name'];
                $relation = $emergencyContact['relationship'];
                $phone = $emergencyContact['phone'];

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
                        'phone' => $phone
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
        /* TODO Fill in these form teacher vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a housemaster from their house name
     *
     * @return array
     */
    private function findHousemaster(): array
    {
        /* TODO Fill in these housemaster vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find legal alerts
     *
     * @return string|null
     */
    private function findLegal(): ?string
    {
        /* TODO Fill in these legal alert vars
         */
        return null;
    }


    /**
     * Find medical data for student
     *
     * @param array|null $medical
     * @return string|null
     */
    private function findMedicalAlert(?array $medical): ?string
    {
        return is_array($medical) ? implode('; ', array_unique($medical)) : null;
    }


    /**
     * Find out if this student has special needs
     *
     * @return bool
     */
    private function findSpecialNeeds(): bool
    {
        /* TODO Fill in these special needs vars
         */
        return false;
    }


    /**
     * Find a tutor from their id
     *
     * @return array
     */
    private function findTutor(): array
    {
        /* TODO Fill in these tutor vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a year level coordinator from their year level
     *
     * @return array
     */
    private function findYearLevelCoordinator(): array
    {
        /* TODO Fill in these ylc vars
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Import parent profile data for a given student
     *
     * @param array             $relation
     * @param UserProfile\Model $studentBaseProfile
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(array $relation, UserProfile\Model $studentBaseProfile): void
    {
        $parents = [$relation['parent']];

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        foreach ($parents as $parent) {
            $externalId = (string)$parent['externalId'];
            $username = (string)$parent['externalId'];
            $title = $parent['title'];
            $firstname = $parent['firstname'];
            $lastname = $parent['lastname'];
            $email = $parent['email'];
            $phoneHome = $parent['phone']['home telephone number'] ?? null;
            $phoneWork = $parent['phone']['work telephone number'] ?? null;
            $phoneMobile = $parent['phone']['mobile'] ?? null;
            $relationship = $parent['relation'];

            /* Disregard inactive parents
             */
            if (!$relation['parent']['active']) {
                $this->log->warning(sprintf(
                    'Skipping base profile for parent %s - parent is inactive',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Parents without externalIds are skipped
             */
            if ('' === $externalId) {
                $this->log->warning(sprintf(
                    'Skipping base profile for parent %s - no externalId detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            /* Parents without usernames are skipped
             */
            if ('' === $username) {
                $this->log->warning(sprintf(
                    'Skipping base profile for parent %s - no username detected',
                    trim("{$firstname} {$lastname}")
                ));

                continue;
            }

            try {
                $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
                $parentBaseProfile = $parentProfile->getBaseProfile();
            } catch (Exception $e) {
                $parentProfile = $this->parentProfileMapper->createModel();
                $parentBaseProfile = $this->baseProfileMapper->createModel();

                $parentProfile->setBaseProfile($parentBaseProfile);
            }

            $parentBaseProfile
                ->setExternalId($externalId)
                ->setTitle($title)
                ->setFirstname($firstname)
                ->setLastname($lastname);

            /* Log it
             */
            $this->log->debug(sprintf(
                'Preparing base profile for parent %s (%s)',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $result = $this->baseProfileMapper->save($parentBaseProfile);

                /* Somehow this didn't save properly; we might be overwhelming our db with all these saves
                 * For some reason it's just happening here, and for Sentral as far as we can tell
                 *
                 * Try again a few times; if we still can't persist it, log message and skip
                 */
                if (!$result[0]) {
                    $this->log->error(sprintf(
                        "Didn't succeed in saving parent base profile for %s %s (%s) - trying again...",
                        $firstname,
                        $lastname,
                        $externalId
                    ));

                    /* Try again a few times...
                     */
                    for ($nRetry = 1; (!$result[0] && $nRetry <= self::MAX_RETRY); $nRetry++) {
                        $result = $this->baseProfileMapper->save($parentBaseProfile);

                        $this->log->error(sprintf(
                            'Retry attempt %s...',
                            $nRetry
                        ));

                        sleep(self::RETRY_SLEEP);
                    }

                    /* If it's still broken, get out of here...
                     */
                    if (!$result[0]) {
                        $this->log->error(sprintf(
                            "Couldn't save parent base profile for %s %s (%s)",
                            $firstname,
                            $lastname,
                            $externalId
                        ));

                        print_r($parentBaseProfile);
                    } else {
                        $this->log->error(sprintf(
                            'Salvaged parent base profile for %s %s (%s) after %s attempt(s) with %s seconds delay',
                            $firstname,
                            $lastname,
                            $externalId,
                            $nRetry,
                            self::RETRY_SLEEP
                        ));

                        print_r($parentBaseProfile);
                    }
                }
            }

            $this->log->debug(sprintf(
                'Added base profile for parent %s (%s) with sis_user_profile_id %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $externalId,
                $parentBaseProfile->getId()
            ));

            $parentProfile
                ->setPhoneHome($phoneHome)
                ->setPhoneWork($phoneWork)
                ->setMobile($phoneMobile)
                ->setEmail($email)
                ->setUsername($username);

            /* Log it
             */
            $this->log->debug(sprintf(
                'Preparing roletype-specific profile for parent %s (%s)',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                $externalId
            ));

            /* Then save it
             */
            if ($this->perform) {
                $result = $this->parentProfileMapper->save($parentProfile);
            }

            $this->log->info(sprintf(
                'Added roletype-specific profile for parent %s (%s) with sis_user_profile_id %s',
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
                $externalId,
                $parentBaseProfile->getId()
            ));

            /* Manufacture relations
             */

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding relation between %s, the %s of %s',
                trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
                trim((string)$relationship),
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $relationship = $this->relationshipMapper->createModel()
                    ->setUserProfile($parentBaseProfile)
                    ->setChildProfile($studentBaseProfile)
                    ->setType((string)$relationship);

                $this->relationshipMapper->save($relationship);
            }
        }
    }


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = 'enrolments/staff?include=person,qualifications&limit=200';

        $lookups['phone'] = (new Phone($this->api))->compile()->getRecords();

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            /* Compile all the pieces
             */
            $staffIterator = new StaffIterator($payload, $lookups);

            foreach ($staffIterator as $staff) {
                $externalId = $staff['externalId'];
                $username = $staff['externalId'];
                $title = $staff['title'];
                $firstname = $staff['firstname'];
                $lastname = $staff['lastname'];
                $building = '';
                $campusName = '';
                $category = $staff['classification'];
                $department = '';
                $email = $staff['email'];
                $phone = $staff['phone']['mobile'] ?? null;
                $positionTitle = $staff['jobTitle'];
                $room = '';

                /* Disregard inactive staff
                 */
                if (!$staff['active']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is inactive',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Disregard departed staff
                 */
                if (self::STAFF_EMPLOYMENT_STATUS_DEPARTED === $staff['employmentStatus']) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - staff is departed',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Staff without usernames are skipped
                 */
                if ('' === $username) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for staff %s - no username detected',
                        trim("{$firstname} {$lastname}")
                    ));

                    continue;
                }

                /* Find out if this externalId already has a staff profile
                 * If one already exists, load it, update it, then save it
                 * Otherwise make a new one for the given id
                 */
                try {
                    $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
                    $staffBaseProfile = $staffProfile->getBaseProfile();
                } catch (Exception $e) {
                    $staffProfile = $this->staffProfileMapper->createModel();
                    $staffBaseProfile = $this->baseProfileMapper->createModel();

                    $staffProfile->setBaseProfile($staffBaseProfile);
                }

                $staffBaseProfile
                    ->setExternalId($externalId)
                    ->setTitle($title)
                    ->setFirstname($firstname)
                    ->setLastname($lastname);

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding base profile for %s',
                    trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
                ));

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
                    ->setPhone($phone)
                    ->setPositionTitle($positionTitle)
                    ->setRoom($room)
                    ->setUsername($username);

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding staff profile for %s',
                    trim("{$staffBaseProfile->getFirstname()} {$staffBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->staffProfileMapper->save($staffProfile);
                }

                /* Prepare for next page if it exists
                 */
                $nextUrl =  $payload['links']['next'] ?? null;
            }
        }
    }
}
