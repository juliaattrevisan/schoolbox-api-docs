<?php
/**
 * ApiTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\MockApi;
use Schoolbox\External\UPS\Batchxml;


class ApiTest extends TestCase
{

    /**
     * MockApi returned at least something
     */
    public function testRetrieveDocument(): void
    {
        $this->assertNotEmpty((new MockApi('', ''))->retrieveDocument());
    }

    public function testValidStructure(): void
    {
        $batchxml = new Batchxml(new MockApi('', ''));

        /* Check that these nodes exist in the xml feed
         */
        $paths = [];

        /* Students, Staff, Parents
         */
        $paths[] = [
            'nodes' => $batchxml->fetchXml('/iSAMS/PupilManager/CurrentPupils/Pupil'),
            'expected' => 173
        ];

        $paths[] = [
            'nodes' => $batchxml->fetchXml('/iSAMS/HRManager/CurrentStaff/StaffMember'),
            'expected' => 146
        ];

        $paths[] = [
            'nodes' => $batchxml->fetchXml('/iSAMS/PupilManager/Contacts/Contact'),
            'expected' => 436
        ];

        /* Campuses
         */
        $paths[] = [
            'nodes' => $batchxml->fetchXml('/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision'),
            'expected' => 3
        ];

        foreach ($paths as $nodeData) {
            $this->assertEquals($nodeData['expected'], \count($nodeData['nodes']));
        }
    }
}


