<?php
/**
 * Profile
 *
 * Imports user_profile data from ISAMS xml feed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\External\Normalise\User\Student;
use Schoolbox\External\UPS\Batchxml;
use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class Profile
{

    /**
     * @var Batchxml
     */
    private $api;


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
     * @param UserMapper        $userMapper
     * @param UserProfileMapper $userProfileMapper
     */
    public function __construct(UserMapper $userMapper, UserProfileMapper $userProfileMapper)
    {
        $this->api = new Batchxml();
        $this->userMapper = $userMapper;
        $this->userProfileMapper = $userProfileMapper;
    }


    /**
     * Import into user_profile
     */
    public function import(): void
    {
        $relations = [];

        /* Find all the parents in this xml feed and build a static relational array
         */
//        $parents = $this->api->fetchXml('/iSAMS/PupilManager/Contacts/Contact');
//
//        foreach ($parents as $parent) {
//            new \Alaress_Probe($parent, $parent->xpath('@PersonGuid'));
////            $parent->xpath('@PersonGuid')
////            $relations[] = 'tset';
//        }

        $students = $this->api->findAllStudents();

        /** @var Student $student */
        foreach ($students as $student) {

            /* Firstly try to find out if the user already exists in schoolbox
             * If they don't exist, we can't assign a profile to them yet
             * We have to wait for the UPS to make this user
             */
            try {
                $user = $this->userMapper->findByExternalId($student->getId());
            } catch (Mapper\Exception $e) {
                continue;
            }

            /* Secondly try to find an existing profile for this user
             * If it already exists, update it; otherwise create it
             */
            try {
                $profile = $this->userProfileMapper->findById($user->getId());
            } catch (Mapper\Exception $e) {
                $profile = $this->userProfileMapper->createModel()->setUserId($user->getId());
            }

            /* Thirdly populate the profile instance with relevant data
             */
            /** @var UserProfile $profile */
            $profile
//                ->setUser($user)
                ->setTitle($student->getTitle())
                ->setGivenName($student->getGivenName())
                ->setPreferredName($student->getPreferredName())
                ->setLastName($student->getLastname())
                ->setGender($student->getGender())
                ->setEmail($student->getEmail())
                ->setDob($student->getDob());

            $profile
                ->setLevel($student->getYear()->getLevel())
                ->setHomegroup($student->getForm()->getDescription())
                ->setHouse($student->getHouse()->getName());

            $parents = $this->findParentsNodes($student);

            new \Alaress_Probe($student->getId(), $parents);
            new \Alaress_Probe('<hr>');
            die();

new \Alaress_Probe($profile, $student);
die();
            /* Then save it
             */
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
    private function findParentsNodes(Student $student)
    {
        /* Find the parents/guardians of this student
         * To do it, we need a specific id value for $student
         * ($student contains multiple ids, some of which seem internal to usams)
         */
        $path = "/iSAMS/PupilManager/CurrentPupils/Pupil[contains(SchoolCode, '{$student->getId()}')]";
        $node = $this->api->fetchXml($path)[0];

        /* This isn't the primary identifier that we use in $student
         * This is some sort of internal relation id in the xml
         */
        $studentId = (string)$node->xpath('@Id')[0];

        /* Use that to find the parents in similar fashion
         * https://stackoverflow.com/questions/3005370/xpath-to-find-nearest-ancestor-element-that-contains-an-element-that-has-an-attr/40333166
         */
        $path = "/iSAMS/PupilManager/Contacts/Contact/Pupils/Pupil[@Id='{$studentId}']/ancestor::*[position()=2]";
        $parents = $this->api->fetchXml($path);

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
                    'mail' => $mailAddress
                ],
                'suburb' => (string)$parent->Address->Town,
                'postcode' => (string)$parent->Address->Postcode,
                'email' => (string)$parent->EmailAddress,
            ];
        }

        return $stack;
    }
}