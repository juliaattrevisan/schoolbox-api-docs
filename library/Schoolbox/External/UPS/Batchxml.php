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

use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise\User;

class Batchxml implements ISync
{

    /**
     * @var Batchxml/Api
     */
    private $api;

    /**
     * Cache the xml that we downloaded so we don't end up re-fetching it
     * if we run more than one roletype's ups
     *
     * @var string
     */
    private $xmlCache;

    /**
     * Batchxml constructor.
     */
    public function __construct()
    {
        $config = new Loader();

        $host = $config->get('external_ups_provider_batchxml_host');
        $key = $config->get('external_ups_provider_batchxml_key');

        $this->api = new Batchxml\Api($host, $key);
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
    private function fetchXml(string $path): array
    {
        if (!$this->xmlCache) {
            $this->xmlCache = $this->api->retrieveDocument();
            echo 'retrieving doc';
        }

        $nodes = new \SimpleXMLElement($this->xmlCache);

        return $nodes->xpath($path);

        /* Faster, for now
         */
//        $source = __DIR__ .'/../../../../www/temp.xml';
//        $xml = file_get_contents($source);
//        $nodes = new SimpleXMLElement($xml);
//        return $nodes->xpath($path);
    }


    /**
     * Hydrate a User\Student() instance from a simplexml node
     *
     * @param \SimpleXMLElement $currentResult
     * @return User\Student
     */
    private function hydrateStudent(\SimpleXMLElement $currentResult): User\Student
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
            ->setCode((string)$currentResult->AcademicHouse);

        $student
            ->getAccount()
            ->setLogin((string)$currentResult->UserName)
            ->setActive(true);

        $student
            ->addEmail((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

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
                ->addEmail((new User\Common\Email())->setAddress((string)$currentResult->SchoolEmailAddress));

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

            yield $guardian;
        }
    }
}