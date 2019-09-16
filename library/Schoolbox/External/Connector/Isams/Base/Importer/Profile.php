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
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
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
        /* Purge old relations before re-inserting them
         */
        if ($this->perform) {
            $this->relationMapper->getDatabase()->query("
                DELETE FROM {$this->relationMapper->getTable()}
            ");
        }

        $students = $this->batchxml->findAllStudents();

        /** @var Student $student */
        foreach ($students as $student) {

            /* Find out if this externalId already has a student profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            try {
                $studentProfile = $this->studentProfileMapper->findByExternalId($student->getId());
                $studentBaseProfile = $studentProfile->getBaseProfile();
            } catch (Exception $e) {
                $studentProfile = $this->studentProfileMapper->createModel();
                $studentBaseProfile = $this->baseProfileMapper->createModel();

                $studentProfile->setBaseProfile($studentBaseProfile);
            }

            $studentBaseProfile
                ->setExternalId($student->getId())
                ->setTitle($student->getTitle())
                ->setFirstname($student->getPreferredName())
                ->setLastname($student->getLastname());

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

            $studentProfile
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
                trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->studentProfileMapper->save($studentProfile);
            }

            /* Find any associated parents
             */
            $parents = $this->findParentsNodes($student);

            /* For every associated parent, find out if they have a parent profile
             * If one already exists, load it, update it, then save it
             * Otherwise make a new one for the given id
             */
            foreach ($parents as $parent) {

                /* Parents without usernames are skipped
                 */
                if (!\strlen($parent['username'])) {
                    $this->log->warning(sprintf(
                        'Skipping base profile for %s - no username detected',
                        trim("{$parent['firstname']} {$parent['lastname']}")
                    ));

                    continue;
                }

                try {
                    $parentProfile = $this->parentProfileMapper->findByExternalId($parent['id']);
                    $parentBaseProfile = $parentProfile->getBaseProfile();
                } catch (Exception $e) {
                    $parentProfile = $this->parentProfileMapper->createModel();
                    $parentBaseProfile = $this->baseProfileMapper->createModel();

                    $parentProfile->setBaseProfile($parentBaseProfile);
                }

                $parentBaseProfile
                    ->setExternalId($parent['id'])
                    ->setTitle($parent['title'])
                    ->setFirstname($parent['firstname'])
                    ->setLastname($parent['lastname']);

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
                    ->setPhoneHome($parent['phone']['home'])
                    ->setPhoneWork($parent['phone']['work'])
                    ->setMobile($parent['phone']['mobile'])
                    ->setEmail($parent['email'])
                    ->setUsername($parent['username']);

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
                    trim($parent['relationship']),
                    trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
                ));

                /* Then save it
                 */
                if ($this->perform) {
                    $relation = $this->relationMapper->createModel()
                        ->setUserProfileId($parentBaseProfile->getId())
                        ->setChildProfileId($studentBaseProfile->getId())
                        ->setType($parent['relationship']);

                    $this->relationMapper->save($relation);
                }
            }
        }

        /* Process staff
         */

        /* Remove any orphaned profiles
         */
        if ($this->perform) {
            $this->log->info('Removing orphaned profiles.');

            (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper
            ))->remove();
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

        /* Stack 'em up
         */
        foreach ($parents as $parent) {
            $stack[] = [
                'id' => trim((string)$parent->xpath('@Id')[0]),
                'title' => trim((string)$parent->Title),
                'firstname' => trim((string)$parent->Forename),
                'lastname' => trim((string)$parent->Surname),
                'relationship' => trim((string)$parent->RelationshipRaw),
                'phone' => [
                    'home' =>  trim((string)$parent->Telephone),
                    'work' => trim((string)$parent->Telephone),
                    'mobile' => trim((string)$parent->Mobile),
                ],
                'username' => trim((string)$parent->EmailAddress),
                'email' => trim((string)$parent->EmailAddress),
            ];
        }

        return $stack;
    }
}
