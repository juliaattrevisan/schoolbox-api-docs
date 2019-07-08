<?php
/**
 * Profile
 *
 * Imports user_profile data from ISAMS xml feed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;
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
     * @var UserProfileMapper
     */
    private $userProfileMapper;


    /**
     * Profile constructor.
     *
     * @param LoggerInterface   $log
     * @param bool              $perform
     * @param Batchxml          $batchxml
     * @param UserMapper        $userMapper
     * @param UserProfileMapper $userProfileMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        UserMapper $userMapper,
        UserProfileMapper $userProfileMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
        $this->userMapper = $userMapper;
        $this->userProfileMapper = $userProfileMapper;
    }


    /**
     * Import into user_profile
     */
    public function import(): void
    {
        $students = $this->batchxml->findAllStudents();

        /** @var Student $student */
        foreach ($students as $student) {

            /* Firstly try to find out if the user already exists in schoolbox
             * If they don't exist, we can't assign a profile to them yet
             * We have to wait for the UPS to make this user
             */
            try {
                $user = $this->userMapper->findByExternalId($student->getId(), Role::STUDENT);
            } catch (Mapper\Exception $e) {
                $this->log->warning(sprintf(
                    'Skipping profile for %s %s (%d) - schoolbox account could not be found',
                    $student->getPreferredName(),
                    $student->getLastname(),
                    $student->getId()
                ));

                continue;
            }

            /* Secondly try to find an existing profile for this user
             * If it already exists, update it; otherwise create it
             */
            try {
                $profile = $this->userProfileMapper->findById($user->getId());
            } catch (Mapper\Exception $e) {
                $profile = $this->userProfileMapper->createModel()->setUser($user);
            }

            /* Thirdly populate the profile instance with relevant data
             */
            /** @var UserProfile $profile */
            $profile
                ->setUser($user)
                ->setTitle($student->getTitle())
                ->setGivenName($student->getGivenName())
                ->setPreferredName($student->getPreferredName())
                ->setLastName($student->getLastname())

                /* Gender is stored as an int(11) !!!! field in our schema, wot
                 * This string will always end up getting cast to zero for now
                 */
                ->setGender($student->getGender())
                ->setEmail($student->getEmail()->first() ? $student->getEmail()->first()->getAddress() : '')
                ->setDob($student->getDob());

            $profile
                ->setLevel($student->getYear()->getLevel())
                ->setHomegroup($student->getForm()->getDescription())
                ->setHouse($student->getHouse()->getName());

            /* Assemble parents into profile instance
             */
            $parents = $this->findParentsNodes($student);

            if (isset($parents[0])) {
                $profile
                    ->setGuardian1Name($parents[0]['name'])
                    ->setGuardian1HomePhone($parents[0]['phone']['home'])
                    ->setGuardian1WorkPhone($parents[0]['phone']['work'])
                    ->setGuardian1MobilePhone($parents[0]['phone']['mobile'])
                    ->setGuardian1Email($parents[0]['email'])
                ;
            }

            if (isset($parents[1])) {
                $profile
                    ->setGuardian2Name($parents[1]['name'])
                    ->setGuardian2HomePhone($parents[1]['phone']['home'])
                    ->setGuardian2WorkPhone($parents[1]['phone']['work'])
                    ->setGuardian2MobilePhone($parents[1]['phone']['mobile'])
                    ->setGuardian2Email($parents[1]['email'])
                ;
            }

            /* TODO: ESL and IB Flags indeterminate
             * But the model doesn't support these columns??
             */

            $this->log->info(sprintf(
                'Adding profile for %s',
                $user->getFullName()
            ));

            /* Then save it
             */
            if ($this->perform) {
                $this->userProfileMapper->save($profile);
            }
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
        $studentId = (string)$node->xpath('@Id')[0];

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
                (string)$parent->Address->AddressLines->Address1,
                (string)$parent->Address->AddressLines->Address2,
                (string)$parent->Address->AddressLines->Address3
            ]));

            /* Full, formal address that you could paste on an envelope
             */
            $mailAddress = implode(', ', [
                $address,
                (string)$parent->Address->Town,
                (string)$parent->Address->Postcode,
                (string)$parent->Address->Country
            ]);

            /* Stack 'em up
             */
            $stack[] = [
                'name' => (string)$parent->Forename . ' ' . (string)$parent->Surname,
                'phone' => [
                    'home' =>  (string)$parent->Telephone,
                    'work' => (string)$parent->Telephone,
                    'mobile' => (string)$parent->Mobile,
                ],
                'address' => [
                    'home' => $address,
                    'mail' => $mailAddress,
                    'suburb' => (string)$parent->Address->Town,
                    'postcode' => (string)$parent->Address->Postcode,
                ],
                'email' => (string)$parent->EmailAddress,
            ];
        }

        return $stack;
    }
}
