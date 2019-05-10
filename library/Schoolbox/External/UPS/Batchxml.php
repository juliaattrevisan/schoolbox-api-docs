<?php
/**
 * Batchxml
 *
 * ISAMS Batch File processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\UPS;

use Schoolbox\Collection;
use Schoolbox\Config\Loader;
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
     */
    public function __construct()
    {
        $config = new Loader();

        $host = $config->get('external_ups_provider_batchxml_host');
        $key = $config->get('external_ups_provider_batchxml_key');

        $this->api = new Api($host, $key);
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

        /* Faster, for now
         */
//        $source = __DIR__ .'/../../../../www/temp.xml';
//        $xml = file_get_contents($source);
//        $this->nodes = new \SimpleXMLElement($xml);

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
                ->setId((string)$currentResult->xpath('@Id')[0])
                ->setCode((string)$currentResult->Code)
                ->setName((string)$currentResult->Name);

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
            ->setId((string)$currentResult->SchoolCode)
            ->setTitle((string)$currentResult->Title)
            ->setGivenName((string)$currentResult->Forename)
            ->setPreferredName((string)$currentResult->Preferredname)
            ->setLastname((string)$currentResult->Surname)
            ->setGender((string)$currentResult->Gender)
            ->setDob(\Alaress_Date_Format::newFromTimestamp(strtotime((string)$currentResult->DOB)));

        $student
            ->getYear()
            ->setDescription((string)$currentResult->NCYear)
            ->setLevel((int)$currentResult->NCYear);

        /* "Form" seems to be some kind of string, not an int
         * so we don't use setLevel()
         */
        $student
            ->getForm()
            ->setDescription((string)$currentResult->Form);
        $student
            ->getHouse()
            ->setName((string)$currentResult->AcademicHouse)
            ->setCode((string)$currentResult->AcademicHouse);

        $student
            ->getAccount()
            ->setLogin((string)$currentResult->UserName)
            ->setActive(true);

        $student
            ->addEmail((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

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
     * {@inheritDoc}
     */
    public function findAllStaff()
    {
        $staffs = $this->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember');

        foreach ($staffs as $currentResult) {
            $staff = new User\Staff();

            $staff
                ->setId((string)$currentResult->UserCode)
                ->setTitle((string)$currentResult->Title)
                ->setPreferredName((string)$currentResult->PreferredName)
                ->setGivenName((string)$currentResult->Forename)
                ->setLastname((string)$currentResult->Surname)
                ->setGender((string)$currentResult->Gender)
                ->setPhoneWork((new User\Common\Phone())->setNumber((string)$currentResult->SchoolMobileNumber))
                ->setPhoneMobile((new User\Common\Phone())->setNumber((string)$currentResult->SchoolMobileNumber))
                ->setPhoneHome((new User\Common\Phone())->setNumber((string)$currentResult->PersonalMobileNumber))
                ->getMeta()
                    ->getPosition()
                        ->setDescription((string)$currentResult->Roles->Role->Name);

            /* Account
             */
            $staff
                ->getAccount()
                    ->setLogin((string)$currentResult->UserName)
                    ->setActive(true);

            /* Email - one for every item in stack
             */
            $staff
                ->getEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->SchoolEmailAddress));

            $staff
                ->getWorkEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->SchoolEmailAddress));

            $staff
                ->getHomeEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->SchoolEmailAddress));

            /* Campus
             */
            foreach ($currentResult->Divisions->Division as $division) {
                $staff->getCampus()->add((new User\Common\Campus())->setName((string)$division->Name));
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
                ->setId((string)$currentResult->xpath('@PersonId')[0]->PersonId)
                ->setTitle((string)$currentResult->Title)
                ->setPreferredName((string)$currentResult->Forename)
                ->setGivenName((string)$currentResult->Forename)
                ->setLastname((string)$currentResult->Surname)
                ->setType((string)$currentResult->Relationship)
                ->setPhoneWork((new User\Common\Phone())->setNumber((string)$currentResult->Telephone))
                ->setPhoneMobile((new User\Common\Phone())->setNumber((string)$currentResult->Mobile));

            /* Account
             */
            $guardian
                ->getAccount()
                    ->setLogin((string)$currentResult->EmailAddress)
                    ->setActive(true);

            /* Email Address
             */
            $guardian
                ->getEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

            $guardian
                ->getWorkEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

            $guardian
                ->getHomeEmail()
                    ->add((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

            /* Find all the pupils for this parent and stack them up
             */
            $allStudents = [];

            foreach ($currentResult->xpath('Pupils/Pupil/@Id') as $studentNode) {
                $studentId = (string)$studentNode;

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