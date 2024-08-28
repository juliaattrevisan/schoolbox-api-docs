<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar\Test;

use Alaress\Lumberjack\Logger;
use External_User;
use Monolog\Logger as Monologger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Normalise\User\Adapter;
use Schoolbox\External\Normalise\User\Student as ExternalStudent;
use Schoolbox\User as InternalUser;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\Sync;
use Schoolbox\UserMapper;

class UserRulesetTest extends TestCase
{
    /** @var Sync\Grammar\UserRuleset */
    protected $ruleset;

    /** @var LoggerInterface */
    protected $logger;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->markTestSkipped(Sync\Grammar\Factory::class . ' currently relies on database');
        $this->logger = new Logger(STDOUT, STDERR, Monologger::DEBUG, [], true);
        $stratFactory = new Sync\Grammar\Factory($this->logger);
        $this->ruleset = $stratFactory->build(Role::STUDENT);
    }


    /**
     * @return array
     */
    public static function dataLogging()
    {
        return [
            [new ExternalStudent(), new InternalUser(),],
        ];
//        $euh = new External_User();
//
//        // students
//        $studentUserAccountInfo = (array)$euh->getStudentUserAccountInfo();
//
//        /** @var ExternalStudent[] $studentData */
//        $studentData = [];
//
//        // collate the student data, because there be lotsa dupes
//        $adapter = new Adapter($euh);
//        $roleIds = RoleMapper::getInstance()->findIdsByColumn('group_type', 'student');
//        $size    = 1;
//
//        for ($i = 0; $i < $size; ++$i) {
//
//            $extId = $studentUserAccountInfo[$i]['StudentID'];
//
//            $existing = UserMapper::getInstance()->findByColumnList([ 'synergy_id' => $extId, 'group_id' => $roleIds ])->first();
//
//            if (!$existing)
//            {
//                // not an internal user - for the sake of testing, skip and get the next one if possible
//                if ($size < count($studentUserAccountInfo)) {
//                    ++$size;
//                }
//                continue;
//            }
//
//            $studentData["user $extId"] = [ $adapter->getStudent($extId), $existing ];
//        }
//
//        return $studentData;
    }


    /**
     * @param ExternalStudent $external
     * @param InternalUser    $internal
     * @return InternalUser
     */
    protected function hydrate($external, $internal = null)
    {
        return $this->ruleset->hydrate($external, $internal);
    }

    /**
     * @param ExternalStudent $external
     * @param InternalUser    $internal
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataLogging')]
    public function testLoggingExisting($external, $internal)
    {
        $this->hydrate($external, $internal);
        echo "\n";
    }


    /**
     * @param ExternalStudent $external
     * @param InternalUser    $internal
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataLogging')]
    public function testLoggingNotExisting($external, $internal)
    {
        $this->hydrate($external);
        echo "\n";
    }
}
