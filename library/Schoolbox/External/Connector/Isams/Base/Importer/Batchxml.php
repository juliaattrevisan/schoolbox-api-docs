<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Schoolbox\External\Connector\Isams\Base\Api;

/**
 * Class Batchxml
 *
 * ISAMS Batch File processor. Reads from an api endpoint, eats the xml there,
 * then caches a xpath-traversable which you can repeatedly call without having
 * to incur the cost of redownloading the xml.
 *
 * The cached xpath-traversable lives for runtime duration, it doesn't write it
 * to disk or anything like that.
 *
 * @package Schoolbox\External\UPS
 */
class Batchxml
{

    /**
     * @var Api
     */
    private $api;


    /**
     * Cache the xml nodes that we downloaded so we don't end up re-fetching it
     * if we run more than one roletype's ups.
     *
     * @var \SimpleXMLElement
     */
    private $nodes;


    /**
     * Batchxml constructor.
     *
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }


    /**
     * Fetch xml contents
     *
     * If it was already downloaded once before, don't download it again
     *
     * @param string $path
     * @return \SimpleXMLElement[]
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function fetchXml(string $path): array
    {
        if (!$this->nodes) {
            $this->nodes = new \SimpleXMLElement($this->api->retrieveDocument());
        }

        return $this->nodes->xpath($path);
    }
//
//
//    /**
//     * Hydrate a User\Student() instance from a simplexml node
//     *
//     * @param \SimpleXMLElement $currentResult
//     * @return User\Student
//     */
//    public function hydrateStudent(\SimpleXMLElement $currentResult): User\Student
//    {
//        $student = new User\Student();
//
//        $student
//            ->setId(null)
//            ->setTitle(null)
//            ->setGivenName(null)
//            ->setPreferredName(null)
//            ->setLastname(null)
//            ->setGender(null)
//            ->setDob(null);
//
//        $student
//            ->getYear()
//                ->setDescription(null)
//                ->setLevel(null);
//
//        /* "Form" seems to be some kind of string, not an int
//         * so we don't use setLevel()
//         */
//        $student
//            ->getForm()
//                ->setDescription(null);
//        $student
//            ->getHouse()
//                ->setName(null)
//                ->setCode(null);
//
//        $student
//            ->getAccount()
//                ->setLogin(null)
//                ->setActive(true);
//
//        $student
//            ->addEmail((new User\Common\Email())->setAddress(null));
//
//        /* Assign campuses if they exist in the mapping
//         * Otherwise quietly disregard bad data
//         */
//        if (isset($this->campusMapping[(int)$currentResult->NCYear])) {
//            $student
//                ->getCampus()
//                   ->add($this->campusMapping[(int)$currentResult->NCYear]);
//        }
//
//        return $student;
//    }
//
//
//    /**
//     * {@inheritDoc}
//     */
//    public function findAllStudents()
//    {
//        $students = $this->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil');
//
//        foreach ($students as $currentResult) {
//            yield $this->hydrateStudent($currentResult);
//        }
//    }
//
//
//    /**
//     * Used in UPS to identify students who belong to a particular parent according to the xml nodes
//     *
//     * @param string $externalId
//     * @return Collection
//     * @throws \RuntimeException
//     * @throws \Zend\Uri\Exception\InvalidArgumentException
//     * @throws \Zend\Uri\Exception\InvalidUriPartException
//     */
//    public function findStudentsOfParent(string $externalId): Collection
//    {
//        $collection = new Collection();
//
//        $studentNodes = $this->fetchXml("/iSAMS/PupilManager/Contacts/Contact[@PersonId='{$externalId}']/Pupils/Pupil");
//
//        /* Find the full details of each student
//         */
//        foreach ($studentNodes as $studentNode) {
//            $studentId = trim((string)$studentNode->xpath('@Id')[0]->Id);
//
//            $node = $this->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];
//
//            if ($node) {
//                $collection->add($this->hydrateStudent($node));
//            }
//        }
//
//        return $collection;
//    }
//
//
//    /**
//     * {@inheritDoc}
//     */
//    public function findAllStaff()
//    {
//        $staffs = $this->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember');
//
//        foreach ($staffs as $currentResult) {
//            $staff = new User\Staff();
//
//            $staff
//                ->setId(null)
//                ->setTitle(null)
//                ->setPreferredName(null)
//                ->setGivenName(null)
//                ->setLastname(null)
//                ->setGender(null)
//                ->setPhoneWork((new User\Common\Phone())->setNumber(null))
//                ->setPhoneMobile((new User\Common\Phone())->setNumber(null))
//                ->setPhoneHome((new User\Common\Phone())->setNumber(null));
//
//            /* Multiple positions
//             */
////            if ($currentResult->Roles->Role) {
////                foreach ($currentResult->Roles->Role as $currentRole) {
////                    $position = (new User\Staff\Position())->setDescription(trim((string)$currentRole->Name));
////
////                    $staff->getMeta()->getPosition()->add($position);
////                }
////            }
//
//            /* Account
//             */
//            $staff
//                ->getAccount()
//                    ->setLogin(null)
//                    ->setActive(true);
//
//            /* Email - one for every item in stack
//             */
//            $staff
//                ->getEmail()
//                    ->add((new User\Common\Email())->setAddress(null));
//
//            $staff
//                ->getWorkEmail()
//                    ->add((new User\Common\Email())->setAddress(null));
//
//            $staff
//                ->getHomeEmail()
//                    ->add((new User\Common\Email())->setAddress(null));
//
//            /* Campus
//             */
//            foreach ($currentResult->Divisions->Division as $division) {
//                $staff->getCampus()->add((new User\Common\Campus())->setName(null));
//            }
//
//            yield $staff;
//        }
//    }
//
//
//    /**
//     * {@inheritDoc}
//     */
//    public function findAllParents()
//    {
//        $guardians = $this->fetchXml('/iSAMS/PupilManager/Contacts/Contact');
//
//        foreach ($guardians as $currentResult) {
//            $guardian = new User\Guardian();
//
//            $guardian
//                ->setId(trim((string)$currentResult->xpath('@PersonId')[0]->PersonId))
//                ->setTitle(trim((string)$currentResult->Title))
//                ->setPreferredName(trim((string)$currentResult->Forename))
//                ->setGivenName(trim((string)$currentResult->Forename))
//                ->setLastname(trim((string)$currentResult->Surname))
//                ->setType(trim((string)$currentResult->Relationship))
//                ->setPhoneWork((new User\Common\Phone())->setNumber(trim((string)$currentResult->Telephone)))
//                ->setPhoneMobile((new User\Common\Phone())->setNumber(trim((string)$currentResult->Mobile)));
//
//            /* Account
//             */
//            $guardian
//                ->getAccount()
//                    ->setLogin(trim((string)$currentResult->EmailAddress))
//                    ->setActive(true);
//
//            /* Email Address
//             */
//            $guardian
//                ->getEmail()
//                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));
//
//            $guardian
//                ->getWorkEmail()
//                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));
//
//            $guardian
//                ->getHomeEmail()
//                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));
//
//            /* Find all the pupils for this parent and stack them up
//             */
//            $allStudents = [];
//
//            foreach ($currentResult->xpath('Pupils/Pupil/@Id') as $studentNode) {
//                $studentId = trim((string)$studentNode);
//
//                /* Find the first instance of a Pupil with Id = $studentId
//                 * For some reason they've duped the data for us
//                 */
//                $node = $this->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']");
//
//                if ($node) {
//                    $student = $node[0];
//
//                    /* Hydrate it into a User/Student() instance
//                     * Ensure de-dupe
//                     */
//                    if (null !== $student) {
//                        $hydratedStudent = $this->hydrateStudent($student);
//
//                        $allStudents[$hydratedStudent->getId()] = $hydratedStudent;
//                    }
//                }
//            }
//
//            /* If we found any students, throw them into a Collection
//             * Then throw the Collection into $guardian
//             * Otherwise the defaults are fine
//             */
//            if (\count($allStudents)) {
//                $guardian->setStudents(new Collection(array_values($allStudents)));
//            }
//
//            yield $guardian;
//        }
//    }
}
