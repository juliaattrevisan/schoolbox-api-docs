<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Profile;

use RuntimeException;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Batchxml;

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
}
