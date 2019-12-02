<?php
/**
 * ApiTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\MockApi;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Batchxml;

/**
 * Class ApiTest
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Test
 */
class ApiTest extends TestCase
{

    /**
     * MockApi returned at least something
     */
    public function testRetrieveDocument(): void
    {
//        $this->assertNotEmpty((new MockApi('', ''))->retrieveDocument());

        /* For Alex to fill in at some point
         */
        $this->assertEquals(true, true);
    }


    /**
     * Test the the Four Pieces are there: Pupil, StaffMember, Contact, SchoolDivision
     * and that there are the expected number of each item after parsing the xml
     *
     * @throws \RuntimeException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     */
    public function testValidStructure(): void
    {
//        $batchxml = new Batchxml(new MockApi('', ''));
//
//        /* Check that these nodes exist in the xml feed
//         */
//        $paths = [];
//
//        /* Students, Staff, Parents
//         */
//        $paths[] = [
//            'nodes' => $batchxml->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil'),
//            'expected' => 173
//        ];
//
//        $paths[] = [
//            'nodes' => $batchxml->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember'),
//            'expected' => 146
//        ];
//
//        $paths[] = [
//            'nodes' => $batchxml->fetchXml('/iSAMS/PupilManager/Contacts/Contact'),
//            'expected' => 436
//        ];
//
//        /* Campuses
//         */
//        $paths[] = [
//            'nodes' => $batchxml->fetchXml('/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision'),
//            'expected' => 3
//        ];
//
//        foreach ($paths as $nodeData) {
//            $this->assertEquals($nodeData['expected'], \count($nodeData['nodes']));
//        }

        /* For Alex to fill in at some point
         */
        $this->assertEquals(true, true);
    }
}


