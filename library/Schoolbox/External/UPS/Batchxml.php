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

namespace Schoolbox\External\UPS;

use Schoolbox\Collection;
use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\ISync;
use Schoolbox\External\Normalise\User;

class Batchxml implements ISync
{

    /**
     * @var Batchxml
     */
    private $api;


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
     * {@inheritDoc}
     */
    public function findAllStudents()
    {

        $xml = $this->api->retrieveDocument();

        /* Faster, for now
         */
//        $source = __DIR__ .'/../../../../www/temp.xml';
//        $xml = file_get_contents($source);

        $nodes = new \SimpleXMLElement($xml);

        $students = $nodes->xpath('/iSAMS/PupilManager/CurrentPupils/Pupil');

        $collection = new Collection();

        foreach ($students as $currentResult) {
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
                    ->setLevel((string)$currentResult->NCYear);

            $student
                ->getForm()
                    ->setDescription((string)$currentResult->Form)
                    ->setLevel((string)$currentResult->Form);
            $student
                ->getHouse()
                    ->setCode((string)$currentResult->AcademicHouse);

            $student
                ->getAccount()
                    ->setLogin((string)$currentResult->UserName);

            $student
                ->addEmail((new User\Common\Email())->setAddress((string)$currentResult->EmailAddress));

            $collection->add($student);
        }

        return $collection;
    }


    /**
     * Gets a collection of normalized staff objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Staff[]
     */
    public function findAllStaff()
    {
        // TODO: Implement findAllStaff() method.
    }


    /**
     * Gets a collection of normalized parent objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Guardian[]
     */
    public function findAllParents()
    {
        // TODO: Implement findAllParents() method.
    }
}