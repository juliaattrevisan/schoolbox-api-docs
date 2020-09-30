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
use RuntimeException;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Normalise\User\Staff;
use Schoolbox\External\Normalise\User\Student;
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
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 100;


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
     * A list of external ids that were seen at various endpoints
     * importStudents() gets a list of relationships so we might
     * end up encountering the same student multiple times
     * but we don't need to reprocess their medical data etc.
     *
     * So only do that to people we haven't seen before
     *
     * @var array
     */
    private $encounters = [];

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

        /* Import students' profile data
         * Import their specific parents' profile data
         */
        $this->log->info('Processing students and their parents.');

        $this->importStudents();

//        /* Import staff profile data
//         */
//        $this->log->info('Processing staff.');
//
//        $this->importStaff();
    }


    /**
     * Import students' profile data
     */
    private function importStudents(): void
    {

        /* Sentral gives you paginated data, at most, 200 per page
         *
         * Process multiple pages until there are none remaining, or if something is really stuffed,
         * until MAX_CALLS is reached, to prevent infinite calls to a third party.
         */
        $nextUrl = 'enrolments/student-contact?include=person,student,studentPerson&limit=200';

        for ($i = 0; $nextUrl && $i < self::MAX_CALLS; $i++) {
            $payload = $this->api->retrieveDocument($nextUrl);

            /** @var Student $student */
            foreach ($payload['data'] as $studentPersonRelation) {
                $externalId = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['externalId'];
                $academicHouse = '';
                $boardingStatus = false;
                $campusName = '';
                $comments = '';
                $dob = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['dateOfBirth'];
                $email = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['email'];
                $firstname = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['firstName'];
                $form = '';
                $gender = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['gender'];
                $lastname = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['lastName'];
                $legal = '';
                $medicalAlert = '';
                $messages = '';
                $mobile = '';
                $studiesCode = '';
                $title = (string)$studentPersonRelation['relationships']['studentPerson']['data']['attributes']['title'];
                $tutor = '';
                $tutorGroup = '';
                $username = '';
                $yearLevel = '';

                /* Dates
                 */
                $enrolmentDate = '';
                $leavingDate = '';

                /* Flags
                 */
                $esl = false;
                $ib = false;
                $ilp = false;
                $specialNeeds = false;

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
                $this->log->info(sprintf(
                    'Adding base profile for %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->baseProfileMapper->save($studentBaseProfile);
                }

                /*  Maintain the methods' return signature for easiest resolution
                 *  though you are free to customise it as you see fit
                 */

                /* Fish out alert comments
                 */
                $alertComments = $this->findComments();

                /* Fish out legal alerts
                 */
                $alertLegal = $this->findLegal();

                /* Fish out campus name
                 */
                $campusName = $this->findCampusName();

                /* Emergency Contacts
                 */
                $emergencyContacts = $this->findEmergencyContacts($studentPersonRelation);

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

                /* If this student was processed earlier, we don't need to do it again
                 * These fetch data from specific endpoints so we can save ourselves some
                 * api requests which result in the same outcome
                 *
                 * (the alternative is to build a lookup after downloading their db table...)
                 */
                if (!isset($this->encounters[$externalId])) {

                    /* Fish out medical alerts
                     */
                    $studentProfile->setAlertMedical($this->findMedicalAlert($studentBaseProfile, $studentPersonRelation));
                }

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
                if (null !== $emergencyContacts) {

                    /* The studentPersonRelation we are currently processing
                     * contains an emergency contact; find the first available
                     * slot, put them in there, then save it
                     */
                    if ('' === (string)$studentProfile->getEmergencyContactName1()) {
                        $studentProfile
                            ->setEmergencyContactName1($emergencyContacts['name'])
                            ->setEmergencyContactPhone1($emergencyContacts['phone']);

                        $this->log->debug(sprintf(
                            '%s (user %s) received emergency contacts in slot 1: %s %s',
                            trim("{$firstname} {$lastname}"),
                            $externalId,
                            $emergencyContacts['name'],
                            $emergencyContacts['phone']
                        ));

                    } elseif ('' === (string)$studentProfile->getEmergencyContactName2()) {
                        $studentProfile
                            ->setEmergencyContactName2($emergencyContacts['name'])
                            ->setEmergencyContactPhone2($emergencyContacts['phone']);

                        $this->log->debug(sprintf(
                            '%s (user %s) received emergency contacts in slot 2: %s %s',
                            trim("{$firstname} {$lastname}"),
                            $externalId,
                            $emergencyContacts['name'],
                            $emergencyContacts['phone']
                        ));
                    } elseif ('' === (string)$studentProfile->getEmergencyContactName3()) {
                        $studentProfile
                            ->setEmergencyContactName3($emergencyContacts['name'])
                            ->setEmergencyContactPhone3($emergencyContacts['phone']);

                        $this->log->debug(sprintf(
                            '%s (user %s) received emergency contacts in slot 3: %s %s',
                            trim("{$firstname} {$lastname}"),
                            $externalId,
                            $emergencyContacts['name'],
                            $emergencyContacts['phone']
                        ));
                    } else {

                        /* If they're all full, warn user - we hardcoded 3 spots
                         * and can't just add more, like we would if we had taken
                         * the time to normalise the data properly
                         */
                        $this->log->warning(sprintf(
                            '%s (user %s) has too many emergency contacts to add: %s %s',
                            trim("{$firstname} {$lastname}"),
                            $externalId,
                            $emergencyContacts['name'],
                            $emergencyContacts['phone']
                        ));
                    }
                }

                /* Log it
                 */
                $this->log->info(sprintf(
                    'Adding student profile for %s',
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $this->studentProfileMapper->save($studentProfile);
                }

                /* Add their parents' profile data
                 *
                 * Each studentPersonRelation comes with a student and one of their parents
                 * so we continually end up reprocessing data, but ok
                 *
                 * A studentPersonRelation may contain a parent or an emergency contact
                 * We only need to add them in one way or the other, not both
                 */
                if (null === $emergencyContacts) {
                    $this->importParentsOfStudent($externalId, $studentBaseProfile, $studentPersonRelation);
                }

                /* Remember we saw this guy and have processed his details
                 * because we'll encounter a student multiple times when
                 * we work with their studentPersonRelations
                 *
                 * We don't need to do it over and over again
                 */
                $this->encounters[$externalId] = true;
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
     * @param string $date
     * @return Alaress_Date_Format|null
     */
    public function findDate(string $date): ?Alaress_Date_Format
    {
        if ($date) {
            return Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param array $studentPersonRelation
     * @return array|null
     */
    public function findEmergencyContacts(array $studentPersonRelation): ?array
    {

        /* In $studentPersonRelation, an emergency contact has an attribute isEmergencyContact
         * set to something other than an empty string or null
         *
         * You get one emergency contact per $studentPersonRelation, at most
         */
        if ('' !== (string)$studentPersonRelation['attributes']['isEmergencyContact']) {
            $firstname = $studentPersonRelation['relationships']['person']['data']['attributes']['firstName'];
            $lastname  = $studentPersonRelation['relationships']['person']['data']['attributes']['lastName'];

            return [
                'name'  => "{$firstname} {$lastname}",
                'phone' => ''
            ];
        }

        return null;
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
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $studentPersonRelation
     * @return string|null
     * @throws \InvalidArgumentException
     */
    private function findMedicalAlert(UserProfile\Model $studentBaseProfile, array $studentPersonRelation): ?string
    {

        /* Find another endpoint who can tell us the answer
         */
        $endpoint = $studentPersonRelation['relationships']['studentPerson']['data']['links']['medicalConditions'];

        /* No endpoint? No way to find out more
         * Just exit, there's no link to medical conditions
         */
        if ('' === (string)$endpoint) {
            return null;
        }

        /* Otherwise keep digging
         */
        $payload = $this->api->retrieveDocument($endpoint);

        $numConditions = \count($payload['data']);

        $this->log->debug(sprintf(
            'Secondary api inspection at %s for medical conditions belonging to %s (user %s) - found %s',
            $endpoint,
            trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}"),
            $studentBaseProfile->getExternalId(),
            $numConditions
        ));

        if ($numConditions) {
            return 'This student has medical conditions.';
        }

        return '';
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
     * @param string            $studentExternalId
     * @param UserProfile\Model $studentBaseProfile
     * @param array             $studentPersonRelation
     * @throws Exception
     * @throws Mapper\DefinitionException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function importParentsOfStudent(string $studentExternalId, UserProfile\Model $studentBaseProfile, array $studentPersonRelation): void
    {

        /* For every associated parent, find out if they have a parent profile
         * If one already exists, load it, update it, then save it
         * Otherwise make a new one for the given id
         */
        $parent = $studentPersonRelation['relationships']['person']['data'];

        $externalId = (string)$parent['attributes']['externalId'];
        $email = (string)$parent['attributes']['email'];
        $firstname = (string)$parent['attributes']['firstName'];
        $lastname = (string)$parent['attributes']['lastName'];
        $phoneHome = '';
        $phoneMobile = '';
        $phoneWork = '';
        $relationship = (string)$studentPersonRelation['attributes']['relationName'];
        $title = (string)$parent['attributes']['title'];
        $username = '';

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
        $this->log->info(sprintf(
            'Adding base profile for %s',
            trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
        ));

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
            'Adding parent profile for %s',
            trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}")
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


    /**
     * Import staff profile data
     */
    private function importStaff(): void
    {
        $staffs = [];

        /** @var Staff $staff */
        foreach ($staffs as $staff) {

            /* TODO Fill in basic staff vars
             */
            $externalId = '';
            $username = '';
            $title = '';
            $firstname = '';
            $lastname = '';
            $building = '';
            $campusName = '';
            $category = '';
            $department = '';
            $email = '';
            $phone = '';
            $positionTitle = '';
            $room = '';

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
        }
    }
}
