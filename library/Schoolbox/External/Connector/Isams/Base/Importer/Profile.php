<?php
/**
 * Profile
 *
 * Imports user_profile data from ISAMS xml feed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Profile implements ImporterInterface
{

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
     * @var Batchxml
     */
    private $batchxml;


    /**
     * @var Usermapper
     */
    private $userMapper;


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
     * @var UserProfile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * Profile constructor.
     *
     * @param LoggerInterface             $log
     * @param bool                        $perform
     * @param Batchxml                    $batchxml
     * @param UserMapper                  $userMapper
     * @param UserProfile\Mapper          $baseProfileMapper
     * @param UserProfile\Student\Mapper  $studentProfileMapper
     * @param UserProfile\Parent\Mapper   $parentProfileMapper
     * @param UserProfile\Staff\Mapper    $staffProfileMapper
     * @param UserProfile\Relation\Mapper $relationMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        UserMapper $userMapper,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relation\Mapper $relationMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->userMapper = $userMapper;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationMapper = $relationMapper;
    }


    /**
     * Import into user_profile
     */
    public function import(): void
    {
        $students = $this->batchxml->findAllStudents();

        /** @var Student $student */
        foreach ($students as $student) {

            /* Find out if this externalId already has a student profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $studentProfile = $this->studentProfileMapper->findByExternalId($student->getId());
                $baseProfile = $studentProfile->getBaseProfile();
            } catch (\Schoolbox\Mapper\Exception $e) {
                $studentProfile = $this->studentProfileMapper->createModel();
                $baseProfile = $this->baseProfileMapper->createModel();

                $studentProfile->setBaseProfile($baseProfile);
            }

            $baseProfile
                ->setExternalId($student->getId())
                ->setTitle($student->getTitle())
                ->setFirstname($student->getPreferredName())
                ->setLastname($student->getLastname());

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding base profile for %s',
                trim("{$baseProfile->getFirstname()} {$baseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->baseProfileMapper->save($baseProfile);
            }

            $studentProfile
                ->setBaseProfile($baseProfile)
                ->setAlertMedical($student->getMedical())
                ->setAlertComment($student->getAlert())
                ->setBoardingStatus((bool)$student->getBoardingHouse())
                ->setCampusName($student->getCampus()->count() ? $student->getCampus()->first()->getName() : null)
                ->setDateOfBirth($student->getDob())
                ->setEmail($student->getEmail()->count() ? $student->getEmail()->first()->getAddress() : null)
                ->setFormName((string)$student->getForm()->getDescription())
                ->setFormTeacherId($student->getForm()->getCoordinator()->getId())
                ->setFormTeacherName(trim("{$student->getForm()->getCoordinator()->getPreferredName()} {$student->getForm()->getCoordinator()->getLastname()}"))
                ->setGender($student->getGender())
                ->setHeadOfHouseId($student->getHouse()->getHousemaster()->getId())
                ->setHeadOfHouseName(trim("{$student->getHouse()->getHousemaster()->getPreferredName()} {$student->getHouse()->getHouseMaster()->getLastname()}"))
                ->setHouseName($student->getHouse()->getName())
                ->setMobile($student->getPhoneMobile()->getNumber())
                ->setSpecialNeeds((bool)$student->getSpecialNeeds())
                ->setTutorGroup($student->getTutor()->getGroup())
                ->setTutorTeacherId($student->getTutor()->getId())
                ->setTutorTeacherName(trim("{$student->getTutor()->getPreferredName()} {$student->getTutor()->getLastname()}"))
                ->setYearLevel((string)$student->getYear()->getLevel())
                ->setYearLevelCoordinatorId($student->getYear()->getCoordinator()->getId())
                ->setYearLevelCoordinatorName(trim("{$student->getYear()->getCoordinator()->getPreferredName()} {$student->getYear()->getCoordinator()->getLastname()}"))
                ->setUsername($student->getAccount()->getLogin())

                /* These fields aren't supported by our Normalise adapter layer
                 * TODO: Students don't have a campus???
                 */
                ->setAlertLegal(null)
                ->setAlertMessage(null)
                ->setEnrolmentDate(null)
                ->setLeavingDate(null)
                ->setStudiesCode(null)
                ->setEsl(null)
                ->setIb(null)
                ->setIndividualLearningPlans(null);

            /* Log it
             */
            $this->log->info(sprintf(
                'Adding student profile for %s',
                trim("{$baseProfile->getFirstname()} {$baseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->studentProfileMapper->save($studentProfile);
            }


            /* Find any associated parents
             * For every associated parent, find out if they have a parent profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */

            /* Remove any orphaned profiles
             * TODO: Reuse the one from CSV Importer
             */



//            /* Firstly try to find out if the user already exists in schoolbox
//             * If they don't exist, we can't assign a profile to them yet
//             * We have to wait for the UPS to make this user
//             */
//            try {
//                $user = $this->userMapper->findByExternalId($student->getId(), Role::STUDENT);
//            } catch (Mapper\Exception $e) {
//                $this->log->warning(sprintf(
//                    'Skipping profile for %s %s (%d) - schoolbox account could not be found',
//                    $student->getPreferredName(),
//                    $student->getLastname(),
//                    $student->getId()
//                ));
//
//                continue;
//            }
//
//            /* Secondly try to find an existing profile for this user
//             * If it already exists, update it; otherwise create it
//             */
//            try {
//                $profile = $this->userProfileMapper->findById($user->getId());
//            } catch (Mapper\Exception $e) {
//                $profile = $this->userProfileMapper->createModel()->setUser($user);
//            }
//
//            /* Thirdly populate the profile instance with relevant data
//             */
//            /** @var UserProfile $profile */
//            $profile
//                ->setUser($user)
//                ->setTitle($student->getTitle())
//                ->setGivenName($student->getGivenName())
//                ->setPreferredName($student->getPreferredName())
//                ->setLastName($student->getLastname())
//
//                /* Gender is stored as an int(11) !!!! field in our schema, wot
//                 * This string will always end up getting cast to zero for now
//                 */
//                ->setGender($student->getGender())
//                ->setEmail($student->getEmail()->first() ? $student->getEmail()->first()->getAddress() : '')
//                ->setDob($student->getDob());
//
//            $profile
//                ->setLevel((string)$student->getYear()->getLevel())
//                ->setHomegroup($student->getForm()->getDescription())
//                ->setHouse($student->getHouse()->getName());
//
//            /* Assemble parents into profile instance
//             */
//            $parents = $this->findParentsNodes($student);
//
//            if (isset($parents[0])) {
//                $profile
//                    ->setGuardian1Name($parents[0]['name'])
//                    ->setGuardian1HomePhone($parents[0]['phone']['home'])
//                    ->setGuardian1WorkPhone($parents[0]['phone']['work'])
//                    ->setGuardian1MobilePhone($parents[0]['phone']['mobile'])
//                    ->setGuardian1Email($parents[0]['email'])
//                ;
//            }
//
//            if (isset($parents[1])) {
//                $profile
//                    ->setGuardian2Name($parents[1]['name'])
//                    ->setGuardian2HomePhone($parents[1]['phone']['home'])
//                    ->setGuardian2WorkPhone($parents[1]['phone']['work'])
//                    ->setGuardian2MobilePhone($parents[1]['phone']['mobile'])
//                    ->setGuardian2Email($parents[1]['email'])
//                ;
//            }
//
//            /* TODO: ESL and IB Flags indeterminate
//             * But the model doesn't support these columns??
//             */
//
//            $this->log->info(sprintf(
//                'Adding profile for %s',
//                $user->getFullName()
//            ));
//
//            /* Then save it
//             */
//            if ($this->perform) {
//                $this->userProfileMapper->save($profile);
//            }
        }

        if (!$this->perform) {
            $this->log->info('Planning finished.');
        }
    }


    /**
     * Find the parents of $student
     *
     * @param Student $student
     * @return array
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    private function findParentsNodes(Student $student): array
    {
        /* Find the parents/guardians of this student
         * To do it, we need a specific id value for $student
         * ($student contains multiple ids, some of which seem internal to usams)
         */
        $path = "/iSAMS/PupilManager/CurrentPupils/Pupil[contains(SchoolCode, '{$student->getId()}')]";
        $node = $this->batchxml->fetchXml($path)[0];

        /* This isn't the primary identifier that we use in $student
         * This is some sort of internal relation id in the xml
         */
        $studentId = trim((string)$node->xpath('@Id')[0]);

        /* Use that to find the parents in similar fashion
         * https://stackoverflow.com/questions/3005370/xpath-to-find-nearest-ancestor-element-that-contains-an-element-that-has-an-attr/40333166
         *
         * TODO: I would think there was alternative syntax than "position()=2" but I don't know what it is
         * If isams changes this structure, this will snap (obviously) so yeah, we should pay attention
         */
        $path = "/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil[@Id='{$studentId}']/ancestor::*[position()=2]";
        $parents = $this->batchxml->fetchXml($path);

        $stack = [];

        foreach ($parents as $parent) {

            /* Implode non-empty address fields into one big string for our schema
             */
            $address = implode(', ', array_filter([
                trim((string)$parent->Address->AddressLines->Address1),
                trim((string)$parent->Address->AddressLines->Address2),
                trim((string)$parent->Address->AddressLines->Address3)
            ]));

            /* Full, formal address that you could paste on an envelope
             */
            $mailAddress = implode(', ', [
                $address,
                trim((string)$parent->Address->Town),
                trim((string)$parent->Address->Postcode),
                trim((string)$parent->Address->Country)
            ]);

            /* Stack 'em up
             */
            $stack[] = [
                'name' => trim((string)$parent->Forename) . ' ' . trim((string)$parent->Surname),
                'phone' => [
                    'home' =>  trim((string)$parent->Telephone),
                    'work' => trim((string)$parent->Telephone),
                    'mobile' => trim((string)$parent->Mobile),
                ],
                'address' => [
                    'home' => $address,
                    'mail' => $mailAddress,
                    'suburb' => trim((string)$parent->Address->Town),
                    'postcode' => trim((string)$parent->Address->Postcode),
                ],
                'email' => trim((string)$parent->EmailAddress),
            ];
        }

        return $stack;
    }
}
