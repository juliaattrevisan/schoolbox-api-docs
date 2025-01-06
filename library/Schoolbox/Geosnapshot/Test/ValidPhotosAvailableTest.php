<?php

/**
 * ValidPhotosAvailableTest
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
use Schoolbox\Geosnapshot\ValidPhotosAvailable;
use Schoolbox\Role;

/**
 * Class ValidPhotosAvailableTest
 *
 * @package Schoolbox\Example\Test
 */
class ValidPhotosAvailableTest extends TestCase
{
    /**
     * @param null|string $apiKey
     * @param bool $featureFlag
     * @param \Schoolbox\User $viewer
     * @param \Schoolbox\User  $viewee
     * @param int $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validPhotosAvailable')]
    public function testValidPhotosAvailable($apiKey, $featureFlag, $viewer, $viewee, $expected)
    {
        $outcome = (new ValidPhotosAvailable())
            ->isSatisifiedBy($apiKey, $featureFlag, $viewer, $viewee);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidPhotosAvailable
     *
     * @return array
     */
    public static function provider_validPhotosAvailable(): array
    {

        /* A classically-correct user
         */
        $edenReed = (new \Schoolbox\User())
            ->setId(6)
            ->setFirstname('Eden')
            ->setLastname('Reed')
            ->setRole((new \Schoolbox\Role())->setType(Role::STUDENT))
            ->setPortrait((new \Schoolbox\Resource\Storage\Model())->setHash('file hash goes here'));

        /* Jana doesn't have a portrait picture
         */
        $janaReed = (new \Schoolbox\User())
            ->setId(25)
            ->setFirstname('Jana')
            ->setLastname('Reed')
            ->setRole((new \Schoolbox\Role())->setType(Role::STUDENT));

        /* A random person outside the Reed family
         */
        $davidRhodes = (new \Schoolbox\User())
            ->setId(230)
            ->setFirstname('David')
            ->setLastname('Rhodes')
            ->setRole((new \Schoolbox\Role())->setType(Role::STUDENT))
            ->setPortrait((new \Schoolbox\Resource\Storage\Model())->setHash('file hash goes here'));

        /* A random person outside the Reed family
         * and they do not have a portrait picture
         */
        $jamalAli = (new \Schoolbox\User())
            ->setId(60)
            ->setFirstname('Jamal')
            ->setLastname('Ali')
            ->setRole((new \Schoolbox\Role())->setType(Role::STUDENT));

        /* A simulated parent
         */
        $jenniReed = (new \Schoolbox\User())
            ->setId(27)
            ->setFirstname('Jenni')
            ->setLastname('Reed')
            ->setRole((new \Schoolbox\Role())->setType(Role::PARENT))
            ->setChildren(new \Schoolbox\GenericCollection(\Schoolbox\User::class));

        /* Install Reed children
         */
        $jenniReed->getChildren()
            ->add($edenReed)
            ->add($janaReed);

        /* A simulated staff
         */
        $jackColes = (new \Schoolbox\User())
            ->setId(22)
            ->setFirstname('Jack')
            ->setLastname('Coles')
            ->setRole((new \Schoolbox\Role())->setType(Role::STAFF));

        return [

            /* Classical scenario
             * Parent of a child can view their photos if they have a portrait
             * picture and all the api setup is there
             */
            'Parent viewing their own child' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                true
            ],

            /* Parent doesn't have access to geosnapshot when child
             * doesn't have a portrait picture
             */
            'Parent viewing their own child but they do not have a portrait' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Jana
                $janaReed,
                false
            ],

            /* Parent can't view another family's child
             */
            'Parent viewing some other guy' => [
                'aa112233', // API Key set
                false, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden, not Dr Hodes
                $davidRhodes,
                false
            ],

            /* Staff can see anyone
             */
            'Staff member viewing a student 1' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $edenReed,
                true
            ],

            /* Staff can see anyone and David is his own person
             */
            'Staff member viewing a student 2' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $davidRhodes,
                true
            ],

            /* Student doesn't have a portrait picture
             * and won't work, even when viewed by a staff
             */
            'Staff member viewing a student 3' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $janaReed, // but Jana doesn't have a picture
                false
            ],

            /* Student doesn't have a portrait picture
             * and won't work, even when viewed by a staff
             */
            'Staff member viewing a student 4' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jackColes, // Jack is a staff member
                $jamalAli, // but Jana doesn't have a picture
                false
            ],

            /* Students can see themselves, but nobody else
             */
            'Student views themselves 1' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $edenReed,
                $edenReed,
                true
            ],

            /* Students can see themselves, but nobody else
             * but they need a profile picture
             */
            'Student views themselves 2' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $janaReed,
                $janaReed,
                false
            ],

            /* Students can see themselves, but nobody else
             */
            'Student views another student' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $edenReed,
                $jamalAli,
                false
            ],

            /* Students can see themselves, but nobody else
             */
            'Student views parent' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $edenReed,
                $jenniReed,
                false
            ],

            /* Students can see themselves, but nobody else
             */
            'Student views staff' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $edenReed,
                $jackColes,
                false
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

            /* The feature flag needs to be enabled
             */
            'Feature flag is enabled' => [
                'abcd-1234-QWERTY-10000', // API Key set
                true, // Feature is enabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                true
            ],

            'Feature flag is disabled' => [
                'abcd-1234-QWERTY-10000', // API Key set
                false, // Feature is disabled in current plan
                $jenniReed, // Jenni is parent of Eden
                $edenReed,
                false
            ],
        ];
    }
}
