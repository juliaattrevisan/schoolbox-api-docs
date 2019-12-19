<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use RuntimeException;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;
use SimpleXMLElement;

/**
 * Class Traverser
 *
 * Traverses the xml looking for particular nodes
 * Each traverser is unique to each xml payload
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile
 */
class Traverser
{

    /**
     * Maximum number of emergency contacts we've permitted (3 by default)
     */
    private const MAX_EMERGENCY_CONTACTS = 3;

    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * Traverser constructor.
     *
     * @param Batchxml $batchxml
     */
    public function __construct(Batchxml $batchxml)
    {
        $this->batchxml = $batchxml;
    }


    /**
     * Convert a date from possibly null/empty value in xml
     *
     * @param string $date
     * @return \Alaress_Date_Format|null
     */
    public function findDate(string $date): ?\Alaress_Date_Format
    {
        if ($date) {
            return \Alaress_Date_Format::newFromTimestamp(strtotime($date));
        }

        return null;
    }


     /**
     * Find a tutor from their id
     *
     * @param string $id
     * @return array
     * @throws RuntimeException
     */
    public function findTutor(string $id): array
    {
        return $this->findStaffMemberById(trim($id));
    }


    /**
     * Find a housemaster from their house name
     *
     * @param string $houseName
     * @return array
     */
    public function findHousemaster(string $houseName): array
    {
        /* afaik Kamar doesn't have any concept of Housemasters
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a form teacher from their form code
     *
     * @param string $form
     * @return array
     */
    public function findFormTeacher(string $form): array
    {
        /* afaik Kamar doesn't have any concept of Forms or Form Teachers
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a year level coordinator from their year level
     *
     * @param string $year
     * @return array
     */
    public function findYearLevelCoordinator(string $year): array
    {
        /* afaik Kamar doesn't have any concept of Yar Level Coordinators
         */
        return [
            'id' => null,
            'name' => null
        ];
    }


    /**
     * Find a staff member's node based on identifier $id
     *
     * @param string $id
     * @return array
     * @throws RuntimeException
     */
    private function findStaffMemberById(string $id): array
    {
        $node = $this->batchxml->fetchXml("/SMSDirectoryData/staff/user[@id='{$id}']");

        $name = '';

        if ($node) {
            $staffMember = $node[0];
            $name = trim((string)$staffMember->firstname) . ' ' . trim((string)$staffMember->lastname);
        }

        return [
            'id' => $id,
            'name' => $name
        ];
    }


    /**
     * Find all the meaningful emergency contacts for a student
     *
     * @param SimpleXMLElement $student
     * @return array
     */
    public function findEmergencyContacts(SimpleXMLElement $student): array
    {
        $contacts = [];
        $n = 0;

        foreach ($student->emergencycontacts->contact as $emergencyContact) {
            if ($n < self::MAX_EMERGENCY_CONTACTS) {
                $fullname = trim((string)$emergencyContact->name);
                $relation = trim((string)$emergencyContact->relationship);
                $phone = trim((string)$emergencyContact->mobile);

                /* Suffix the $relation if provided
                 */
                if ('' !== $relation) {
                    $fullname .= " ({$relation})";
                }

                /* Aggregate them if we have a name
                 */
                if ('' !== $fullname) {
                    $contacts[] = [
                        'name' => $fullname,
                        'phone' => $phone
                    ];
                }
            }

            $n++;
        }

        return $contacts;
    }
}
