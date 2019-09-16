<?php
/**
 * Batchxml
 *
 * ISAMS Batch File processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\UPS;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Isams\Base\Api;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise\User;

class Batchxml implements ISync
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
     * A mapping between (NCYear, Campus) instances
     *
     * Campuses are stored separately from Students and are joined on NCYear
     * so create a lookup instead of traversing every time
     *
     * @var array
     */
    private $campusMapping = [];


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

        /* Prefetch (Campus, NCYear) relations as a lookup
         */
        if (!\count($this->campusMapping)) {
            $this->campusMapping = $this->prefetchCampuses();
        }

        return $this->nodes->xpath($path);
    }


    /**
     * Mine $nodes for Campus elements and the NCYear levels to which they relate
     *
     * @return array
     */
    private function prefetchCampuses(): array
    {
        $campuses = $this->nodes->xpath('/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision');

        $mapping = [];

        foreach ($campuses as $currentResult) {
            $campus = (new User\Common\Campus())
                ->setId(trim((string)$currentResult->xpath('@Id')[0]))
                ->setCode(trim((string)$currentResult->Code))
                ->setName(trim((string)$currentResult->Name));

            /* Map campuses to their NCYear levels
             */
            $years = $currentResult->xpath('Years/Year[@YearId]');

            foreach ($years as $year) {
                $mapping[(int)$year->xpath('@YearId')[0]->YearId] = $campus;
            }
        }

        return $mapping;
    }


    /**
     * Hydrate a User\Student() instance from a simplexml node
     *
     * @param \SimpleXMLElement $currentResult
     * @return User\Student
     */
    public function hydrateStudent(\SimpleXMLElement $currentResult): User\Student
    {
        $student = new User\Student();

        $student
            ->setId(trim((string)$currentResult->SchoolCode))
            ->setTitle(trim((string)$currentResult->Title))
            ->setGivenName(trim((string)$currentResult->Forename))
            ->setPreferredName(trim((string)$currentResult->Preferredname))
            ->setLastname(trim((string)$currentResult->Surname))
            ->setGender(trim((string)$currentResult->Gender))
            ->setDob(\Alaress_Date_Format::newFromTimestamp(strtotime(trim((string)$currentResult->DOB))));

        $student
            ->getYear()
            ->setDescription(trim((string)$currentResult->NCYear))
            ->setLevel((int)$currentResult->NCYear);

        /* "Form" seems to be some kind of string, not an int
         * so we don't use setLevel()
         */
        $student
            ->getForm()
            ->setDescription(trim((string)$currentResult->Form));
        $student
            ->getHouse()
            ->setName(trim((string)$currentResult->AcademicHouse))
            ->setCode(trim((string)$currentResult->AcademicHouse));

        $student
            ->getAccount()
            ->setLogin(trim((string)$currentResult->SchoolCode))
            ->setActive(true);

        $student
            ->addEmail((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));

        /* Assign campuses if they exist in the mapping
         * Otherwise quietly disregard bad data
         */
        if (isset($this->campusMapping[(int)$currentResult->NCYear])) {
            $student
                ->getCampus()
                   ->add($this->campusMapping[(int)$currentResult->NCYear]);
        }

        return $student;
    }


    /**
     * {@inheritDoc}
     */
    public function findAllStudents()
    {
        $students = $this->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil');

        foreach ($students as $currentResult) {
            yield $this->hydrateStudent($currentResult);
        }
    }


    /**
     * Used in UPS to identify students who belong to a particular parent according to the xml nodes
     *
     * @param string $externalId
     * @return Collection
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function findStudentsOfParent(string $externalId): Collection
    {
        $collection = new Collection();

        $studentNodes = $this->fetchXml("/iSAMS/PupilManager/Contacts/Contact[@PersonId='{$externalId}']/Pupils/Pupil");

        /* Find the full details of each student
         */
        foreach ($studentNodes as $studentNode) {
            $studentId = trim((string)$studentNode->xpath('@Id')[0]->Id);

            $node = $this->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']")[0];

            if ($node) {
                $collection->add($this->hydrateStudent($node));
            }
        }

        return $collection;
    }


    /**
     * {@inheritDoc}
     */
    public function findAllStaff()
    {
        $staffs = $this->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember');

        foreach ($staffs as $currentResult) {
            $staff = new User\Staff();

            $staff
                ->setId(trim((string)$currentResult->xpath('@PersonId')[0]->PersonId))
                ->setTitle(trim((string)$currentResult->Title))
                ->setPreferredName(trim((string)$currentResult->PreferredName))
                ->setGivenName(trim((string)$currentResult->Forename))
                ->setLastname(trim((string)$currentResult->Surname))
                ->setGender(trim((string)$currentResult->Gender))
                ->setPhoneWork((new User\Common\Phone())->setNumber(trim((string)$currentResult->SchoolMobileNumber)))
                ->setPhoneMobile((new User\Common\Phone())->setNumber(trim((string)$currentResult->SchoolMobileNumber)))
                ->setPhoneHome((new User\Common\Phone())->setNumber(trim((string)$currentResult->PersonalMobileNumber)));

            /* Multiple positions
             */
            if ($currentResult->Roles->Role) {
                foreach ($currentResult->Roles->Role as $currentRole) {
                    $position = (new User\Staff\Position())->setDescription(trim((string)$currentRole->Name));

                    $staff->getMeta()->getPosition()->add($position);
                }
            }

            /* Account
             */
            $staff
                ->getAccount()
                    ->setLogin(trim((string)$currentResult->UserName))
                    ->setActive(true);

            /* Email - one for every item in stack
             */
            $staff
                ->getEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->SchoolEmailAddress)));

            $staff
                ->getWorkEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->SchoolEmailAddress)));

            $staff
                ->getHomeEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->SchoolEmailAddress)));

            /* Campus
             */
            foreach ($currentResult->Divisions->Division as $division) {
                $staff->getCampus()->add((new User\Common\Campus())->setName(trim((string)$division->Name)));
            }

            yield $staff;
        }
    }


    /**
     * {@inheritDoc}
     */
    public function findAllParents()
    {
        $guardians = $this->fetchXml('/iSAMS/PupilManager/Contacts/Contact');

        foreach ($guardians as $currentResult) {
            $guardian = new User\Guardian();

            $guardian
                ->setId(trim((string)$currentResult->xpath('@PersonId')[0]->PersonId))
                ->setTitle(trim((string)$currentResult->Title))
                ->setPreferredName(trim((string)$currentResult->Forename))
                ->setGivenName(trim((string)$currentResult->Forename))
                ->setLastname(trim((string)$currentResult->Surname))
                ->setType(trim((string)$currentResult->Relationship))
                ->setPhoneWork((new User\Common\Phone())->setNumber(trim((string)$currentResult->Telephone)))
                ->setPhoneMobile((new User\Common\Phone())->setNumber(trim((string)$currentResult->Mobile)));

            /* Account
             */
            $guardian
                ->getAccount()
                    ->setLogin(trim((string)$currentResult->EmailAddress))
                    ->setActive(true);

            /* Email Address
             */
            $guardian
                ->getEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));

            $guardian
                ->getWorkEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));

            $guardian
                ->getHomeEmail()
                    ->add((new User\Common\Email())->setAddress(trim((string)$currentResult->EmailAddress)));

            /* Find all the pupils for this parent and stack them up
             */
            $allStudents = [];

            foreach ($currentResult->xpath('Pupils/Pupil/@Id') as $studentNode) {
                $studentId = trim((string)$studentNode);

                /* Find the first instance of a Pupil with Id = $studentId
                 * For some reason they've duped the data for us
                 */
                $node = $this->fetchXml("/iSAMS/PupilManager/CurrentPupils/Pupil[@Id='{$studentId}']");

                if ($node) {
                    $student = $node[0];

                    /* Hydrate it into a User/Student() instance
                     * Ensure de-dupe
                     */
                    if (null !== $student) {
                        $hydratedStudent = $this->hydrateStudent($student);

                        $allStudents[$hydratedStudent->getId()] = $hydratedStudent;
                    }
                }
            }

            /* If we found any students, throw them into a Collection
             * Then throw the Collection into $guardian
             * Otherwise the defaults are fine
             */
            if (\count($allStudents)) {
                $guardian->setStudents(new Collection(array_values($allStudents)));
            }

            yield $guardian;
        }
    }
}
