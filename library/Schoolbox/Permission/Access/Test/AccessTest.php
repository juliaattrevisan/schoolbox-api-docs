<?php

/** @noinspection DuplicatedCode */

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Permission\Access\Test;

use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql;
use Alaress\DataStructures\ObjectTree;
use Error;
use External_UserInterface;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Role;
use Schoolbox\User;
use UnexpectedValueException;
use function array_merge;


/**
 * Test the Access class with hard-coded data state:
 *
 * Folder Tree:
 * id=0
 * └-- id=1, inherit=0, userPerm[2=>0,3=>0,4=>1,5=>1,6=>2,7=>0,8=>3,9=>0]
 *     |-- id=2, inherit=0, rolePerm=[staff=>0,student=>1,parent=>2,guest=>0]
 *     |   └-- id=4, inherit=0, rolePerm=[staff=>2,student=>0,parent=>1,guest=>0], code='ABC', userPerm[2=>1,3=>1,4=>2,5=>1,6=>3,7=>3]
 *     |       └-- id=5, inherit=1
 *     └-- id=3, inherit=1
 *
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Permission\Access\Access::class)]
class AccessTest extends TestCase
{
    // Default hard-coded system state for this test. Individual tests can override.
    private const CONFIG = [
        ConfigMapper::NAME_ALLOW_STUDENT_CLASS_ACCESS => true,
        ConfigMapper::NAME_EXTERNAL_TYPE              => 'synergetic',
    ];

    private const FOLDER_1_ID = 1;
    private const FOLDER_2_ID = 2;
    private const FOLDER_3_ID = 3;
    private const FOLDER_4_ID = 4;
    private const FOLDER_5_ID = 5;

    private const FOLDER_DATA = [
        // id, parent, inherit
        [self::FOLDER_1_ID, Folder\Model::ROOT_ID, false],
        [self::FOLDER_2_ID, self::FOLDER_1_ID, false],
        [self::FOLDER_3_ID, self::FOLDER_1_ID, true],
        [self::FOLDER_4_ID, self::FOLDER_2_ID, false],
        [self::FOLDER_5_ID, self::FOLDER_4_ID, true],
    ];

    private const ROLE_1_ID = 1;
    private const ROLE_2_ID = 2;
    private const ROLE_3_ID = 3;
    private const ROLE_4_ID = 4;

    private const ROLE_DATA = [
        [self::ROLE_1_ID, Role::STAFF],
        [self::ROLE_2_ID, Role::STUDENT],
        [self::ROLE_3_ID, Role::PARENT],
        [self::ROLE_4_ID, Role::GUEST],
    ];

    private const FOLDER_CODE_DATA = [
        // folderId, code
        [self::FOLDER_4_ID, 'ABC'],
    ];

    private const CALENDER_JUNIOR_ID = 1;
    private const CALENDER_SENIOR_ID = 2;

    private const USER_1_ID = 1;
    private const USER_2_ID = 2;
    private const USER_3_ID = 3;
    private const USER_4_ID = 4;
    private const USER_5_ID = 5;
    private const USER_6_ID = 6;
    private const USER_7_ID = 7;
    private const USER_8_ID = 8;
    private const USER_9_ID = 9;

    private const USER_DATA = [
        // id, roleId, su
        [self::USER_1_ID, self::ROLE_1_ID, true],
        [self::USER_2_ID, self::ROLE_1_ID, false],
        [self::USER_3_ID, self::ROLE_2_ID, false],
        [self::USER_4_ID, self::ROLE_2_ID, false],
        [self::USER_5_ID, self::ROLE_2_ID, false],
        [self::USER_6_ID, self::ROLE_3_ID, false],
        [self::USER_7_ID, self::ROLE_3_ID, false],
        [self::USER_8_ID, self::ROLE_4_ID, false],
        [self::USER_9_ID, self::ROLE_4_ID, false],
    ];

    private const USER_ACCESS_DATA = [
        // userId, service, identifier, perm

        // Group perms
        [self::USER_2_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
        [self::USER_3_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
        [self::USER_4_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_READ],
        [self::USER_5_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_READ],
        [self::USER_6_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_WRITE],
        [self::USER_7_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
        [self::USER_8_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
        [self::USER_9_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],

        [self::USER_2_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
        [self::USER_3_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
        [self::USER_4_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
        [self::USER_5_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
        [self::USER_6_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_DENY],
        [self::USER_7_ID, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_DENY],

        // Calendar perms
        [self::USER_2_ID, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_READ],
        [self::USER_3_ID, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_WRITE],

        // CMS perms
        [self::USER_8_ID, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_FULL],
        [self::USER_9_ID, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_WRITE],
    ];

    private const ROLE_ACCESS_DATA = [
        // folderId, roleId, perm
        [self::FOLDER_2_ID, self::ROLE_1_ID, UserAccess::ACCESS_DENY],
        [self::FOLDER_2_ID, self::ROLE_2_ID, UserAccess::ACCESS_READ],
        [self::FOLDER_2_ID, self::ROLE_3_ID, UserAccess::ACCESS_WRITE],
        [self::FOLDER_2_ID, self::ROLE_4_ID, UserAccess::ACCESS_DENY],
        [self::FOLDER_4_ID, self::ROLE_1_ID, UserAccess::ACCESS_WRITE],
        [self::FOLDER_4_ID, self::ROLE_2_ID, UserAccess::ACCESS_DENY],
        [self::FOLDER_4_ID, self::ROLE_3_ID, UserAccess::ACCESS_READ],
        [self::FOLDER_4_ID, self::ROLE_4_ID, UserAccess::ACCESS_DENY],
    ];


    // These users are all staff or students
    private const EXTERNAL_USER_DATA = [
        // userId, code, campus
        [self::USER_2_ID, 'ABC', ''],
        [self::USER_3_ID, 'ABC', 'X'],
        [self::USER_3_ID, 'XXX', ''],
        [self::USER_4_ID, 'ABC', ''],
        [self::USER_4_ID, 'XXX', ''],
        [self::USER_4_ID, 'X/ABC', ''],
        [self::USER_5_ID, 'Y/ABC', 'X'],
    ];

    private const ENABLE_CACHE = true;


    // Tests
    /**
     * @param Access       $access
     * @param User         $user
     * @param Folder\Model $folder
     * @param bool         $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsInstructor')]
    public function testIsInstructor(Access $access, User $user, Folder\Model $folder, bool $expected): void
    {
        $this->assertSame($expected, $access->isInstructor($user, $folder));
    }


    /**
     * @param Access       $access
     * @param User         $user
     * @param Folder\Model $folder
     * @param bool         $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsLearner')]
    public function testIsLearner(Access $access, User $user, Folder\Model $folder, bool $expected): void
    {
        $this->assertSame($expected, $access->isLearner($user, $folder));
    }


    /**
     * @param Access $access
     * @param User   $user
     * @param int    $service
     * @param int    $serviceIdentifier
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetAccessLevel')]
    public function testGetAccessLevel(Access $access, User $user, int $service, int $serviceIdentifier, int $expected): void
    {
        $this->assertSame($expected, $access->getAccessLevel($user, $service, $serviceIdentifier));
    }


    /**
     * Tests that an exception is throws if we attempt to fetch an access level for a service that does not use
     * identifiers, but we specified one
     *
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Mapper\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     *
     */
    public function testGetAccessLevelInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newAccess()->getAccessLevel(new User(), UserAccess::SERVICE_CMS, 4);
    }


    /**
     * @param Access $access
     * @param string $roleType
     * @param int    $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSisClassRoleTypeAccess')]
    public function testSisClassRoleTypeAccess(Access $access, string $roleType, int $expected): void
    {
        $this->assertSame($expected, $access->getSisClassRoleTypeAccess($roleType));
    }


    /**
     * @param Access $access
     * @param User   $user
     * @param array  $folderIds
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetMinFolderAccess')]
    public function testGetMinFolderAccess(Access $access, User $user, array $folderIds, int $expected): void
    {
        $this->assertSame($expected, $access->getMinFolderAccess($user, $folderIds));
    }


    /**
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Mapper\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     *
     */
    public function testGetMinFolderAccessInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newAccess()->getMinFolderAccess(new User(), []);
    }


    /**
     * @param Access $access
     * @param User   $user
     * @param string $codeString
     * @param bool   $expected
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvolvedInSubject')]
    public function testInvolvedInSubject(Access $access, User $user, string $codeString, bool $expected): void
    {
        $this->assertSame($expected, $access->involvedInSubject($user, $codeString));
    }


    /**
     * Test that a user without an external ID always has no subject involvement
     *
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws LogicException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function testInvolvedInSubjectNoExternalId(): void
    {
        $this->assertFalse($this->newAccess()->involvedInSubject(new User(), 'ABC'));
    }


    /**
     * @param Access $access
     * @param User   $user
     * @param int    $folderId
     * @param bool   $expected
     * @throws InvalidColumnException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvolvedInGroup')]
    public function testInvolvedInGroup(Access $access, User $user, int $folderId, bool $expected): void
    {
        $this->assertSame($expected, $access->involvedInGroup($user, $folderId));
    }


    /**
     * @param Access $access
     * @param User   $user
     * @param int    $folderId
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetFolderAccess')]
    public function testGetFolderAccess(Access $access, User $user, int $folderId, int $expected): void
    {
        $this->assertSame($expected, $access->getFolderAccess($user, $folderId));
    }


    /**
     * For completeness, tests FULL folder role perms. (This is currently not possible via the UI)
     *
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Mapper\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function testGetFolderAccessFullRolePerm(): void
    {
        $rolePerms = [
            [self::FOLDER_2_ID, self::ROLE_3_ID, UserAccess::ACCESS_FULL],
        ];
        $access    = $this->newAccess(null, null, null, null, null, null, $rolePerms);
        $user      = (new User())->setId(self::USER_1_ID)->setRole((new Role())->setId(self::ROLE_3_ID));

        $this->assertSame(UserAccess::ACCESS_FULL, $access->getFolderAccess($user, self::FOLDER_2_ID));
    }


    /**
     * @param Access $access
     * @param int    $roleId
     * @param int    $folderId
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\InsufficientHydrationException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetFolderAccessForRole')]
    public function testGetFolderAccessForRole(Access $access, int $roleId, int $folderId, int $expected): void
    {
        $this->assertSame($expected, $access->getFolderAccessForRole($roleId, $folderId));
    }


    /**
     * Tests that if we attempt to connect to ExternalUser at run-time in tests, that it fails
     * (ie, no connection to external DB in unit tests)
     *
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     *
     */
    public function testCannotConnectToExternalUser(): void
    {
        $this->expectException(DatabaseException::class);
        $access = $this->newAccess();
        $reflection = new ReflectionClass($access);
        $instance   = $reflection->getProperty('externalUser');
        $instance->setAccessible(true);
        $instance->setValue($access, null);
        $instance->setAccessible(false);

        $access->involvedInSubject((new User())->setExternalId('abc'), 'xyz');
    }


    // Data Providers

    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerIsInstructor(): array
    {
        $access  = $this->newAccess();
        $roles   = $this->buildRoles(self::ROLE_DATA);
        $users   = $this->buildUsers($roles, self::USER_DATA);
        $folders = $this->buildFolderTree(self::FOLDER_DATA);

        return [
            // User1 is SU. Is instructor for all folders
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User2 is staff. Folder4 has role perm for staff of WRITE. Folder5 inherits from Folder4.
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User3 is student. No WRITE+ perm anywhere
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User4 is student. WRITE perm to Folder4 via GroupPerm in `userAccess` table
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User5 is student.
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User6 is parent. Parent role has WRITE to Folder2. WRITE perm via `userAccess` to Folder1.
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User7 is parent. Parent role has WRITE to Folder2.
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User8 is guest. Has CMS FULL perm. Has FULL access to Folder1 via `userAccess`. Folder3 inherits.
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User9 is guest. Has CMS WRITE perm
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerIsLearner(): array
    {
        $access  = $this->newAccess();
        $roles   = $this->buildRoles(self::ROLE_DATA);
        $users   = $this->buildUsers($roles, self::USER_DATA);
        $folders = $this->buildFolderTree(self::FOLDER_DATA);

        return [
            // User1 is SU. Has FULL access to all folders
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), false],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_1_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User2 is staff.
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_2_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User3 is student.
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_3_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User4 is student.
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_4_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User5 is student.
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_1_ID), true],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_2_ID), true],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_3_ID), true],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_5_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User6 is parent.
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_6_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User7 is parent.
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_4_ID), true],
            [$access, $users->getOffset(self::USER_7_ID), $folders->getElementByHash(self::FOLDER_5_ID), true],

            // User8 is guest.
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_8_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],

            // User9 is guest. Has CMS WRITE perm
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(Folder\Model::ROOT_ID), true],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_1_ID), false],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_2_ID), false],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_3_ID), false],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_4_ID), false],
            [$access, $users->getOffset(self::USER_9_ID), $folders->getElementByHash(self::FOLDER_5_ID), false],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerGetAccessLevel(): array
    {
        $access = $this->newAccess();
        $roles  = $this->buildRoles(self::ROLE_DATA);
        $users  = $this->buildUsers($roles, self::USER_DATA);

        $user1 = $users->getOffset(self::USER_1_ID);
        $user2 = $users->getOffset(self::USER_2_ID);
        $user3 = $users->getOffset(self::USER_3_ID);
        $user4 = $users->getOffset(self::USER_4_ID);
        $user5 = $users->getOffset(self::USER_5_ID);
        $user6 = $users->getOffset(self::USER_6_ID);
        $user7 = $users->getOffset(self::USER_7_ID);
        $user8 = $users->getOffset(self::USER_8_ID);
        $user9 = $users->getOffset(self::USER_9_ID);

        // Access $access, User $user, int $service, int $serviceIdentifier, int $expected
        return [
            // User1 is SU. Has FULL access to all folders
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_FULL],
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_FULL],
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_FULL],
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_FULL],
            [$access, $user1, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_FULL],

            // User2 is staff.
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $user2, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // User3 is student.
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $user3, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User4 is student.
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_READ],
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_READ],
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $user4, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // User5 is student.
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_READ],
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_READ],
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $user5, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User6 is parent.
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_WRITE],
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_WRITE],
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $user6, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User7 is parent.
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $user7, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User8 is guest. Has CMS FULL perm.
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_FULL],
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_FULL],
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_FULL],
            [$access, $user8, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_FULL],

            // User9 is guest. Has CMS WRITE perm
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_1_ID, UserAccess::ACCESS_WRITE],
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_3_ID, UserAccess::ACCESS_WRITE],
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $user9, UserAccess::SERVICE_HOMEPAGE, self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // Calendar perms

            [$access, $user1, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user1, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_READ],
            [$access, $user2, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_WRITE],
            [$access, $user4, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user4, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user5, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user5, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user6, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user6, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user8, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user8, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user9, UserAccess::SERVICE_CALENDAR, self::CALENDER_JUNIOR_ID, UserAccess::ACCESS_DENY],
            [$access, $user9, UserAccess::SERVICE_CALENDAR, self::CALENDER_SENIOR_ID, UserAccess::ACCESS_DENY],

            [$access, $user1, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user4, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user5, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user6, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user8, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_FULL],
            [$access, $user9, UserAccess::SERVICE_CMS, UserAccess::SID_NONE, UserAccess::ACCESS_WRITE],

            [$access, $user1, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user2, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user3, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user4, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user5, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user6, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user7, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user8, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
            [$access, $user9, UserAccess::SERVICE_NEWS, UserAccess::SID_NONE, UserAccess::ACCESS_DENY],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerSisClassRoleTypeAccess(): array
    {
        $accessStudentRead = $this->newAccess();
        $accessStudentDeny = $this->newAccess(array_merge(self::CONFIG, ['allow_student_class_access' => false]));

        return [
            // Access $access, string $roleType, int $expected
            [$accessStudentRead, Role::STAFF, UserAccess::ACCESS_WRITE],
            [$accessStudentRead, Role::STUDENT, UserAccess::ACCESS_READ],
            [$accessStudentDeny, Role::STUDENT, UserAccess::ACCESS_DENY],
            [$accessStudentRead, Role::PARENT, UserAccess::ACCESS_DENY],
            [$accessStudentRead, Role::GUEST, UserAccess::ACCESS_DENY],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerGetMinFolderAccess(): array
    {
        $access = $this->newAccess();

        $roles = $this->buildRoles(self::ROLE_DATA);
        $users = $this->buildUsers($roles, self::USER_DATA);

        $user1Folders = [Folder\Model::ROOT_ID, self::FOLDER_1_ID];
        $user2Folders = [self::FOLDER_3_ID, self::FOLDER_4_ID];
        $user3Folders = [self::FOLDER_4_ID];
        $user4Folders = [self::FOLDER_3_ID, self::FOLDER_4_ID];
        $user5Folders = [self::FOLDER_1_ID, self::FOLDER_2_ID, self::FOLDER_3_ID, self::FOLDER_4_ID, self::FOLDER_5_ID];
        $user6Folders = [self::FOLDER_3_ID, self::FOLDER_4_ID];
        $user7Folders = [self::FOLDER_2_ID];
        $user8Folders = [self::FOLDER_1_ID, self::FOLDER_3_ID];
        $user9Folders = [self::FOLDER_3_ID, self::FOLDER_4_ID];

        return [
            // Access $access, User $user, array $folderIds, int $expected
            [$access, $users->getOffset(self::USER_1_ID), $user1Folders, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_2_ID), $user2Folders, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_3_ID), $user3Folders, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_4_ID), $user4Folders, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), $user5Folders, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_6_ID), $user6Folders, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_7_ID), $user7Folders, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_8_ID), $user8Folders, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_9_ID), $user9Folders, UserAccess::ACCESS_WRITE],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerInvolvedInSubject(): array
    {
        $access = $this->newAccess();
        $roles  = $this->buildRoles(self::ROLE_DATA);
        $users  = $this->buildUsers($roles, self::USER_DATA);

        return [
            // Access $access, User $user, string $codeString, bool $expected

            // Not involved in any subjects
            [$access, $users->getOffset(self::USER_1_ID), '', false],
            [$access, $users->getOffset(self::USER_1_ID), 'ABC', false],
            [$access, $users->getOffset(self::USER_1_ID), 'X/ABC', false],

            // In ['','ABC']
            [$access, $users->getOffset(self::USER_2_ID), '', false],
            [$access, $users->getOffset(self::USER_2_ID), 'ABC', true],
            [$access, $users->getOffset(self::USER_2_ID), 'ABC,someOther', true],
            [$access, $users->getOffset(self::USER_2_ID), 'X/ABC', false],
            [$access, $users->getOffset(self::USER_2_ID), 'XXX', false],

            // In [X,ABC],['',XXX]
            [$access, $users->getOffset(self::USER_3_ID), 'ABC', true],
            [$access, $users->getOffset(self::USER_3_ID), 'ABC,someOther', true],
            [$access, $users->getOffset(self::USER_3_ID), 'X/ABC', true],
            [$access, $users->getOffset(self::USER_3_ID), 'XXX', true],
            [$access, $users->getOffset(self::USER_3_ID), 'ABC/ABC', false],

            // In ['',ABC],['',XXX],['','X/ABC']
            [$access, $users->getOffset(self::USER_4_ID), 'ABC', true],
            [$access, $users->getOffset(self::USER_4_ID), 'ABC,someOther', true],
            [$access, $users->getOffset(self::USER_4_ID), 'X/ABC', true],
            [$access, $users->getOffset(self::USER_4_ID), 'A/X/ABC', false],
            [$access, $users->getOffset(self::USER_4_ID), 'XXX', true],
            [$access, $users->getOffset(self::USER_4_ID), 'ABC/ABC', false],

            // In ['X',Y/ABC]
            [$access, $users->getOffset(self::USER_5_ID), 'X/X/ABC', false],
            [$access, $users->getOffset(self::USER_5_ID), 'ABC,Y/ABC', true],
            [$access, $users->getOffset(self::USER_5_ID), 'X/ABC', false],
            [$access, $users->getOffset(self::USER_5_ID), 'Y/ABC', true],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerInvolvedInGroup(): array
    {
        $access = $this->newAccess();
        $roles  = $this->buildRoles(self::ROLE_DATA);
        $users  = $this->buildUsers($roles, self::USER_DATA);

        return [
            // Access $access, User $user, int $folderId, bool $expected
            [$access, $users->getOffset(self::USER_1_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_1_ID, false],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_4_ID, false],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_2_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_1_ID, false],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_4_ID, true],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_3_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_1_ID, false],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_4_ID, true],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_4_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_1_ID, true],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_4_ID, true],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_5_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_1_ID, true],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_4_ID, true],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_6_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_1_ID, true],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_4_ID, false],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_7_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_1_ID, false],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_4_ID, false],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_8_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_1_ID, true],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_4_ID, false],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_5_ID, false],

            [$access, $users->getOffset(self::USER_9_ID), Folder\Model::ROOT_ID, false],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_1_ID, false],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_2_ID, false],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_3_ID, false],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_4_ID, false],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_5_ID, false],

        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerGetFolderAccess(): array
    {
        $access = $this->newAccess();
        $roles  = $this->buildRoles(self::ROLE_DATA);
        $users  = $this->buildUsers($roles, self::USER_DATA);

        return [
            // Access $access, User $user, int $folderId, int $expected

            // User1 is SU. Has FULL access to all folders
            // Intentionally repeats the first scenario to test the cache
            [$access, $users->getOffset(self::USER_1_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_2_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_3_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_4_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_1_ID), self::FOLDER_5_ID, UserAccess::ACCESS_FULL],

            // User2 is staff.
            [$access, $users->getOffset(self::USER_2_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_2_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_2_ID), self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // User3 is student.
            [$access, $users->getOffset(self::USER_3_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_3_ID), self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User4 is student.
            [$access, $users->getOffset(self::USER_4_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_1_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_3_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_4_ID), self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // User5 is student.
            [$access, $users->getOffset(self::USER_5_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_1_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_3_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_5_ID), self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User6 is parent.
            [$access, $users->getOffset(self::USER_6_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_1_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_3_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_6_ID), self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User7 is parent.
            [$access, $users->getOffset(self::USER_7_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_7_ID), self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // User8 is guest. Has CMS FULL perm
            [$access, $users->getOffset(self::USER_8_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_1_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_2_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_3_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_4_ID, UserAccess::ACCESS_FULL],
            [$access, $users->getOffset(self::USER_8_ID), self::FOLDER_5_ID, UserAccess::ACCESS_FULL],

            // User9 is guest. Has CMS WRITE perm
            [$access, $users->getOffset(self::USER_9_ID), Folder\Model::ROOT_ID, UserAccess::ACCESS_READ],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_1_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_3_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, $users->getOffset(self::USER_9_ID), self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerGetFolderAccessForRole(): array
    {
        $access = $this->newAccess();

        return [
            // Access $access, int $roleId, int $folderId, int $expected

            // Staff
            [$access, self::ROLE_1_ID, Folder\Model::ROOT_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_1_ID, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_1_ID, self::FOLDER_2_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_1_ID, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_1_ID, self::FOLDER_4_ID, UserAccess::ACCESS_WRITE],
            [$access, self::ROLE_1_ID, self::FOLDER_5_ID, UserAccess::ACCESS_WRITE],

            // Student
            [$access, self::ROLE_2_ID, Folder\Model::ROOT_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_2_ID, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_2_ID, self::FOLDER_2_ID, UserAccess::ACCESS_READ],
            [$access, self::ROLE_2_ID, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_2_ID, self::FOLDER_4_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_2_ID, self::FOLDER_5_ID, UserAccess::ACCESS_DENY],

            // Parent
            [$access, self::ROLE_3_ID, Folder\Model::ROOT_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_3_ID, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_3_ID, self::FOLDER_2_ID, UserAccess::ACCESS_WRITE],
            [$access, self::ROLE_3_ID, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_3_ID, self::FOLDER_4_ID, UserAccess::ACCESS_READ],
            [$access, self::ROLE_3_ID, self::FOLDER_5_ID, UserAccess::ACCESS_READ],

            // Guest
            [$access, self::ROLE_4_ID, Folder\Model::ROOT_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_4_ID, self::FOLDER_1_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_4_ID, self::FOLDER_2_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_4_ID, self::FOLDER_3_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_4_ID, self::FOLDER_4_ID, UserAccess::ACCESS_DENY],
            [$access, self::ROLE_4_ID, self::FOLDER_5_ID, UserAccess::ACCESS_DENY],

        ];
    }


    // Helpers

    /**
     * Returns a new instance of Access with caches cleared
     *
     * Supports optional overriding of default hard-coded state
     *
     * @param array|null $config
     * @param array|null $folderData
     * @param array|null $folderCodeData
     * @param array|null $roleData
     * @param array|null $userData
     * @param array|null $userAccessData
     * @param array|null $roleAccessData
     * @param array|null $externalUserData
     * @param bool       $enableCache
     * @return Access
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    private function newAccess(
        array $config = null,
        array $folderData = null,
        array $folderCodeData = null,
        array $roleData = null,
        array $userData = null,
        array $userAccessData = null,
        array $roleAccessData = null,
        array $externalUserData = null,
        bool $enableCache = self::ENABLE_CACHE
    ): Access {
        // Reset the singleton instance
        $reflection = new ReflectionClass(Access::class);
        $instance   = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $instance->setAccessible(false);

        // Build default hard-coded system state if not explicitly specified
        $folderTree = $this->buildFolderTree($folderData ?? self::FOLDER_DATA);
        $roles      = $this->buildRoles($roleData ?? self::ROLE_DATA);
        $users      = $this->buildUsers($roles, $userData ?? self::USER_DATA);

        // Create and return new singleton instance
        $mockFolderMapper = $this->getMockFolderMapper($folderTree);
        return Access::getInstance(
            $this->getMockUserAccessMapper($users, $userAccessData ?? self::USER_ACCESS_DATA),
            $this->getMockRoleAccessMapper($folderTree, $roles, $roleAccessData ?? self::ROLE_ACCESS_DATA),
            $mockFolderMapper,
            $this->getMockFolderCodeMapper($folderTree, $folderCodeData ?? self::FOLDER_CODE_DATA),
            $this->getMockExternalUserInterface($externalUserData ?? self::EXTERNAL_USER_DATA),
            $this->getMockIConfigLoader($config ?? self::CONFIG),
            (new Folder\Code\ClassCodeMatchFactory())->build('synergetic'),
            $enableCache,
            Folder\NNIAN::getInstance($mockFolderMapper)
        );
    }


    /**
     * Builds and returns a folder tree
     *
     * @param array $folderData
     * @return ObjectTree<Folder\Model>
     */
    private function buildFolderTree(array $folderData): ObjectTree
    {
        $tree = new ObjectTree(static function (Folder\Model $folder): int {
            return $folder->getId();
        });

        $root = (new Folder\Model())
            ->setId(Folder\Model::ROOT_ID)
            ->setName('Root')
            ->setInherit(false);
        $tree->addElement($root);

        foreach ($folderData as [$id, $parent, $inherit]) {
            $folder = (new Folder\Model())
                ->setId($id)
                ->setName("Folder $id")
                ->setInherit($inherit);
            $group  = (new Group())
                ->setType(Group::PRIVATE_GROUP)
                ->setNotificationDisabled(true)
                ->setForceFollow(false)
                ->setFolder($folder);
            $folder->setGroup($group);
            $tree->addElement($folder);
        }

        foreach ($folderData as [$id, $parent, $inherit]) {
            /** @var Folder\Model $folder */
            /** @var Folder\Model $parent */
            $folder = $tree->getElementByHash($id);
            $parent = $tree->getElementByHash($parent);
            $folder->setParent($parent);
            $tree->addElement($folder, $parent);
        }

        return $tree;
    }


    /**
     * Returns a collection of roles, indexed by role.id
     *
     * @param array $roleData
     * @return GenericCollection<Role>|Role[]
     * @throws UnexpectedValueException
     */
    private function buildRoles(array $roleData): GenericCollection
    {
        $roles = Role::createCollection();
        foreach ($roleData as [$id, $type]) {
            $roles->setOffset(
                $id,
                (new Role())
                    ->setId($id)
                    ->setType($type)
                    ->setName("A $type role")
            );
        }
        return $roles;
    }


    /**
     * Returns a collection of users, indexed by user.id
     *
     * @param GenericCollection<Role>|Role[] $roles
     * @param array                          $userData
     * @return GenericCollection<User>|User[]
     * @throws UnexpectedValueException
     */
    private function buildUsers(GenericCollection $roles, array $userData): GenericCollection
    {
        $users = User::createCollection();
        foreach ($userData as [$id, $roleId, $su]) {
            $users->setOffset(
                $id,
                (new User())
                    ->setId($id)
                    ->setRole($roles->getOffset($roleId))
                    ->setSuperuser($su)
                    ->setExternalId("ex_$id")
            );
        }
        return $users;
    }


    /**
     * Returns a mock UserAccessMapper with hard-coded method return values
     *
     * @param GenericCollection<User>|User[] $users          Indexed by user.id
     * @param array|null                     $userAccessData If specified, overrides default data
     * @return UserAccessMapper
     * @throws Framework\Exception
     */
    private function getMockUserAccessMapper(GenericCollection $users, ?array $userAccessData): UserAccessMapper
    {
        /** @var MockObject|UserAccessMapper $userAccessMapper */
        $userAccessMapper = $this->createMock(UserAccessMapper::class);

        /** @var UserAccess[][][][] $allUserAccess */
        $allUserAccess = [];
        foreach ($userAccessData ?? self::USER_ACCESS_DATA as [$userId, $service, $identifier, $perm]) {
            $allUserAccess[$userId][$service][$identifier] = [
                (new UserAccess())
                    ->setUser($users->getOffset($userId))
                    ->setService($service)
                    ->setServiceIdentifier($identifier)
                    ->setAccessLevel($perm),
            ];
        }

        $userAccessMapper->method('findByColumnList')
                         ->willReturnCallback(static function (array $columnList) use ($allUserAccess): GenericCollection {
                             $userId      = $columnList['uid'];
                             $serviceType = $columnList['service'];
                             $serviceId   = isset($columnList['sid']) ? $columnList['sid'] : UserAccess::SID_NONE;
                             return new GenericCollection(
                                 UserAccess::class,
                                 $allUserAccess[$userId][$serviceType][$serviceId] ?? []
                             );
                         });

        $userAccessMapper->method('findByPredicates')
                         ->willReturnCallback(static function (Builder $pb) use ($allUserAccess): GenericCollection {
                             $params             = $pb->getParams();
                             $userId             = $params['uid_0'];
                             $serviceType        = $params['service_0'];
                             $serviceId          = $params['sid_0'];
                             $type               = $params['type_0'];
                             $matchingUserAccess = $allUserAccess[$userId][$serviceType][$serviceId] ?? [];
                            foreach ($matchingUserAccess as $idx => $userAccess) {
                                if ($userAccess->getAccessLevel() === $type) {
                                    unset($matchingUserAccess[$idx]);
                                }
                            }
                             return new GenericCollection(UserAccess::class, $matchingUserAccess);
                         });

        $userAccessMapper->method('getPredicateBuilder')
                         ->willReturnCallback(static function () {
                             return new Builder(new MySql\Quoter(), 'userAccessMapper');
                         });

        return $userAccessMapper;
    }


    /**
     * Returns a mock Folder Mapper whose methods will simulate a specific folder tree
     *
     * @param ObjectTree $folderTree
     * @return Folder\Mapper
     * @throws Framework\Exception
     */
    private function getMockFolderMapper(ObjectTree $folderTree): Folder\Mapper
    {
        /** @var MockObject|Folder\Mapper $folderMapper */
        $folderMapper = $this->createMock(Folder\Mapper::class);

        /** @see \Schoolbox\Resource\Folder\Mapper::findCheaplyById */
        $folderMapper->method('findCheaplyById')
                     ->willReturnCallback(static function (int $folderId) use ($folderTree): Folder\Model {
                         /** @noinspection PhpIncompatibleReturnTypeInspection */
                         return $folderTree->getElementByHash($folderId);
                     });

        /** @see \Schoolbox\Resource\Folder\Mapper::createCollection */
        $folderMapper->method('createCollection')
                     ->willReturnCallback(static function (array $contents = []): GenericCollection {
                         return new GenericCollection(Folder\Model::class, $contents);
                     });

        return $folderMapper;
    }


    /**
     * Returns a mock FolderCodeMapper whose methods will return hard-coded values
     *
     * @param ObjectTree $folderTree
     * @param array      $folderCodeData
     * @return Folder\Code\Mapper
     * @throws Framework\Exception
     * @throws UnexpectedValueException
     */
    private function getMockFolderCodeMapper(ObjectTree $folderTree, array $folderCodeData): Folder\Code\Mapper
    {
        /** @var MockObject|Folder\Code\Mapper $folderCodeMapper */
        $folderCodeMapper = $this->createMock(Folder\Code\Mapper::class);

        /** @var Folder\Model $folder */
        foreach ($folderTree->toFlatArray() as $folder) {
            // Ensure all folder code collections are set
            if ($folder->getCodes()) {
                // if setting code, unset group to test access based on code
                $folder->setCodes($folder->getCodes())->setGroup(null);
            } else {
                $folder->setCodes(Folder\Code\Model::createCollection());
            }
        }

        foreach ($folderCodeData as [$folderId, $code]) {
            /** @var Folder\Model $folder */
            $folder     = $folderTree->getElementByHash($folderId);
            $folderCode = (new Folder\Code\Model())
                ->setFolder($folder)
                ->setCode($code);
            $folder->getCodes()->setOffset($code, $folderCode);
        }

        $folderCodeMapper->method('findForCategory')
                         ->willReturnCallback(static function (int $folderId) use ($folderTree): GenericCollection {
                             /** @var Folder\Model $folder */
                             $folder = $folderTree->getElementByHash($folderId);
                             return $folder->getCodes();
                         });
        return $folderCodeMapper;
    }


    /**
     * Returns a mock External_UserInterface whose methods will return hard-coded values
     *
     * @param array $externalUserData
     * @return External_UserInterface
     * @throws Framework\Exception
     */
    private function getMockExternalUserInterface(array $externalUserData): External_UserInterface
    {
        /** @var MockObject|External_UserInterface $externalUserInterface */
        $externalUserInterface = $this->createMock(External_UserInterface::class);

        $subjectLists = [];
        foreach ($externalUserData as [$userId, $code, $campus]) {
            $subjectLists[$userId][] = [
                'code'       => $code,
                'name'       => "Subject ['$campus','$code']",
                'classType'  => 'academic',
                'campusCode' => $campus,
            ];
        }

        $externalUserInterface->method('getSubjectList')
                              ->willReturnCallback(static function (User $user) use ($subjectLists): array {
                                  return $subjectLists[$user->getId()] ?? [];
                              });
        return $externalUserInterface;
    }


    /**
     * Returns a mock IConfigLoader whose methods will return hard-coded values
     *
     * @param array $config
     * @return IConfigLoader
     */
    private function getMockIConfigLoader(array $config): IConfigLoader
    {
        return new ArrayLoader($config);
    }


    /**
     * Returns a mock RoleAccessMapper whose methods will return hard-coded values
     *
     * @param ObjectTree              $folderTree
     * @param GenericCollection<Role> $roles
     * @param array                   $roleAccessData
     * @return RoleAccessMapper
     * @throws Framework\Exception
     */
    private function getMockRoleAccessMapper(ObjectTree $folderTree, GenericCollection $roles, array $roleAccessData): RoleAccessMapper
    {
        /** @var MockObject|RoleAccessMapper $roleAccessMapper */
        $roleAccessMapper = $this->createMock(RoleAccessMapper::class);

        /**
         * Indexed by folderId, roleId
         * @var RoleAccess[][][] $roleAccess
         */
        $roleAccess = [];
        foreach ($roleAccessData as [$folderId, $roleId, $perm]) {
            /** @var Folder\Model $folder */
            $folder                         = $folderTree->getElementByHash($folderId);
            $roleAccess[$folderId][$roleId] = [
                (new RoleAccess())
                    ->setCategory($folder)
                    ->setRole($roles->getOffset($roleId))
                    ->setType($perm),
            ];
        }

        $roleAccessMapper->method('findByColumnList')
                         ->willReturnCallback(static function (array $columnList) use ($roleAccess): GenericCollection {
                             $folderId = $columnList['cid'];
                             $roleId   = $columnList['gid'];
                             return new GenericCollection(RoleAccess::class, $roleAccess[$folderId][$roleId] ?? []);
                         });

        return $roleAccessMapper;
    }
}
