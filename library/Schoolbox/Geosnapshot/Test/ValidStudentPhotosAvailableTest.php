<?php
/**
 * ValidGeosnapshotAvailableTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Geosnapshot\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Geosnapshot\ValidStudentPhotosAvailable;
use Schoolbox\Role;


/**
 * Class ValidStudentPhotosAvailableTest
 *
 * @package Schoolbox\Example\Test
 */
class ValidStudentPhotosAvailableTest extends TestCase
{

    /**
     * @dataProvider provider_validStudentPhotosAvailable
     * @param null|string $apiKey
     * @param bool $featureFlag
     * @param \Schoolbox\User $viewer
     * @param \Schoolbox\User  $viewee
     * @param int $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testValidStudentPhotosAvailable($apiKey, $featureFlag, $viewer, $viewee, $expected)
    {

        $outcome = (new ValidStudentPhotosAvailable())
            ->isSatisifiedBy($apiKey, $featureFlag, $viewer, $viewee);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidStudentPhotosAvailable
     *
     * @return array
     */
    public function provider_validStudentPhotosAvailable() : array
    {

        $edenReed = (new \Schoolbox\User())
            ->setId(6)
            ->setFirstname('Eden')
            ->setLastname('Reed');

        /* TODO Add jana without a picture, because you need a picture to succeed at this stuff
         */

        $davidRhodes = (new \Schoolbox\User())
            ->setId(230)
            ->setFirstname('David')
            ->setLastname('Rhodes');

        $jenniReed = (new \Schoolbox\User())
            ->setId(27)
            ->setFirstname('Jenni')
            ->setLastname('Reed')
            ->setChildren(new \Schoolbox\GenericCollection(\Schoolbox\User::class));

        /* Install a child
         */
        $jenniReed->getChildren()
            ->add($edenReed);

        $jackColes = (new \Schoolbox\User())
            ->setId(22)
            ->setFirstname('Jack')
            ->setLastname('Coles')
            ->setRole((new \Schoolbox\Role())->setType(Role::STAFF));

        return [

            /* Add other cases that you expect to be valid and allow connectivity
             */
            'Parent viewing their own child' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                true
            ],

            'Staff member viewing a student 1' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $edenReed,
                true
            ],

            'Staff member viewing a student 2' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $davidRhodes,
                true
            ],

            /* These cases should fail and deny connectivity
             */
            'API Key is null' => [
                null, // API Key missing
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                false
            ],

            'API Key is an empty string' => [
                '', // API Key missing
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                false
            ],

            'Parent viewing some other guy' => [
                'aa112233', // API Key set
                false, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden, not Dr Hodes
                $davidRhodes,
                false
            ],
        ];
    }
}
