<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Permission\Access\Test;

use Alaress\DataStructures\ObjectTree;
use External_UserInterface;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use ReflectionClass;
use ReflectionException;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use UnexpectedValueException;


/**
 * Test the Access class with hard-coded data state:
 *
 * Folder Tree:
 * id=0
 * └-- id=1, inherit=0
 *     |-- id=2, inherit=0, rolePerm=[1=>0,2=>1,3=>2,4=>0]
 *     |   └-- id=4, inherit=0, rolePerm=[1=>2,2=>0,3=>1,4=>0], code='ABC'
 *     |       └-- id=5, inherit=1
 *     └-- id=3, inherit=1
 *
 * Users: TODO
 * - id:1, su=0, roleId=1
 * - id:2, su=0, roleId=2
 * - id:3, su=0, roleId=3
 *
 * Roles:
 * -id:1, type=staff
 * -id:2, type=student
 * -id:3, type=parent
 * -id:4, type=guest
 *
 * TODO document other data, either here, or inline with code
 *
 * @covers \Schoolbox\Permission\Access\Access
 */
class AccessTest extends TestCase
{
    // Tests

    /**
     * @dataProvider providerIsInstructor
     * @param Access       $access
     * @param User         $user
     * @param Folder\Model $folder
     * @param bool         $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     */
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
     */
    public function testIsLearner(Access $access, User $user, Folder\Model $folder, bool $expected): void
    {
        $this->assertSame($expected, $access->isLearner($user, $folder));
    }

    /**
     * @param Access $access
     * @param User   $user
     * @param array  $folderIds
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     */
    public function testGetMinFolderAccess(Access $access, User $user, array $folderIds, int $expected): void
    {
        $this->assertSame($expected, $access->getMinFolderAccess($user, $folderIds));
    }

    /**
     * @param Access $access
     * @param User   $user
     * @param int    $service
     * @param int    $serviceIdentifier
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     */
    public function testGetAccessLevel(Access $access, User $user, int $service, int $serviceIdentifier, int $expected): void
    {
        $this->assertSame($expected, $access->getAccessLevel($user, $service, $serviceIdentifier));
    }

    /**
     * @param Access $access
     * @param string $roleType
     * @param int    $expected
     */
    public function testGetRoleTypeAccess(Access $access, string $roleType, int $expected): void
    {
        $this->assertSame($expected, $access->getSisClassRoleTypeAccess($roleType));
    }

    /**
     * @param Access $access
     * @param User   $user
     * @param string $codeString
     * @param bool   $expected
     */
    public function testInvolvedInSubject(Access $access, User $user, string $codeString, bool $expected): void
    {
        $this->assertSame($expected, $access->involvedInSubject($user, $codeString));
    }

    /**
     * @param Access $access
     * @param User   $user
     * @param int    $serviceIdentifier
     * @param bool   $expected
     */
    public function testInvolvedInGroup(Access $access, User $user, int $serviceIdentifier, bool $expected): void
    {
        $this->assertSame($expected, $access->involvedInGroup($user, $serviceIdentifier));
    }

    /**
     * @param Access $access
     * @param User   $user
     * @param int    $folderId
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     */
    public function testGetFolderAccess(Access $access, User $user, int $folderId, int $expected): void
    {
        $this->assertSame($expected, $access->getFolderAccess($user, $folderId));
    }


    /**
     * @param Access $access
     * @param int    $roleId
     * @param int    $folderId
     * @param int    $expected
     * @throws Mapper\Exception
     */
    public function testGetFolderAccessForRole(Access $access, int $roleId, int $folderId, int $expected): void
    {
        $this->assertSame($expected, $access->getFolderAccessForRole($roleId, $folderId));
    }


    // Data Providers

    /**
     * @return array
     * @throws Framework\Exception
     * @throws LogicException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerIsInstructor(): array
    {
        // Access       $access
        // User         $user
        // Folder\Model $folder
        // bool         $expected
        $access = $this->newAccess(true);
        return [
            [
                $access,
                (new User)->setId(1)->setSuperuser(true),
                (new Folder\Model())->setId(1)->setInherit(false),
                true,
            ],
            [
                $access,
                (new User)->setId(2)->setSuperuser(false),
                (new Folder\Model())->setId(1)->setInherit(false),
                false,
            ],
        ];
    }

    // Helpers

    /**
     * Returns a new instance of Access with caches cleared
     *
     * @param bool $allowStudentClassAccess Whether the system is configured to allow student class access
     * @return Access
     * @throws Framework\Exception
     * @throws LogicException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    private function newAccess(bool $allowStudentClassAccess): Access
    {
        // Reset the singleton instance
        $reflection = new ReflectionClass(Access::class);
        $property   = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
        $property->setAccessible(false);

        $folderTree = $this->buildFolderTree();
        $roles      = $this->buildRoles();

        return Access::getInstance(
            $this->getMockUserAccessMapper(),
            $this->getMockRoleAccessMapper($folderTree, $roles),
            $this->getMockFolderMapper($folderTree),
            $this->getMockFolderCodeMapper(),
            $this->getMockExternalUserInterface(),
            $this->getMockIConfigLoader($allowStudentClassAccess)
        );
    }


    /**
     * Builds and returns and folder tree
     *
     * @return ObjectTree<Folder\Model>
     */
    private function buildFolderTree(): ObjectTree
    {
        $tree = new ObjectTree(static function (Folder\Model $folder): int {
            return $folder->getId();
        });

        $f0 = (new Folder\Model())
            ->setId(0)
            ->setName('Root')
            ->setInherit(false);
        $f1 = (new Folder\Model())
            ->setId(1)
            ->setName('Folder 1')
            ->setInherit(false)
            ->setParent($f0);
        $f2 = (new Folder\Model())
            ->setId(2)
            ->setName('Folder 2')
            ->setInherit(false)
            ->setParent($f1);
        $f3 = (new Folder\Model())
            ->setId(3)
            ->setName('Folder 3')
            ->setInherit(true)
            ->setParent($f1);
        $f4 = (new Folder\Model())
            ->setId(4)
            ->setName('Folder 4')
            ->setInherit(false)
            ->setParent($f2);
        $f5 = (new Folder\Model())
            ->setId(5)
            ->setName('Folder 5')
            ->setInherit(true)
            ->setParent($f4);

        $tree->addElement($f0);
        $tree->addElement($f1, $f1->getParent());
        $tree->addElement($f2, $f2->getParent());
        $tree->addElement($f3, $f3->getParent());
        $tree->addElement($f4, $f4->getParent());
        $tree->addElement($f5, $f5->getParent());

        return $tree;
    }


    /**
     * @return GenericCollection<Role>
     * @throws UnexpectedValueException
     */
    private function buildRoles(): GenericCollection
    {
        return new GenericCollection(Role::class, [
                1 => (new Role())->setId(1)->setType(Role::STAFF),
                2 => (new Role())->setId(2)->setType(Role::STUDENT),
                3 => (new Role())->setId(3)->setType(Role::PARENT),
                4 => (new Role())->setId(4)->setType(Role::GUEST),
            ]
        );
    }

    /**
     * Returns a mock UserAccessMapper with hard-coded method return values
     *
     * @return UserAccessMapper
     * @throws Framework\Exception
     */
    private function getMockUserAccessMapper(): UserAccessMapper
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|UserAccessMapper $userAccessMapper */
        $userAccessMapper = $this->createMock(UserAccessMapper::class);

        // TODO - insert hard-coded data and document in class docblock
        $userAccess = [];

        $userAccessMapper->method('findByColumnList')
                         ->willReturnCallback(static function (array $columnList) use ($userAccess): GenericCollection {
                             $userId      = $columnList['uid'];
                             $serviceType = $columnList['service'];
                             $serviceId   = $columnList['sid'];
                             return new GenericCollection(UserAccess::class, $userAccess[$userId][$serviceType][$serviceId]);
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
        /** @var PHPUnit_Framework_MockObject_MockObject|Folder\Mapper $folderMapper */
        $folderMapper = $this->createMock(Folder\Mapper::class);

        $folderMapper->method('findCheaplyById')
                     ->willReturnCallback(static function (int $folderId) use ($folderTree): Folder\Model {
                         /** @noinspection PhpIncompatibleReturnTypeInspection */
                         return $folderTree->getElementByHash($folderId);
                     });
        $folderMapper->method('findNearestNonInheritingAncestralNode')
                     ->willReturnCallback(static function (Folder\Model $folder) use ($folderTree): Folder\Model {
                         while ($folder->getInherit()) {
                             $folder = $folderTree->getElementByHash($folder->getParent()->getId());
                         }
                         return $folder;
                     });
        return $folderMapper;
    }

    /**
     * Returns a mock FolderCodeMapper whose methods will return hard-coded values
     *
     * @return Folder\Code\Mapper
     * @throws Framework\Exception
     */
    private function getMockFolderCodeMapper(): Folder\Code\Mapper
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|Folder\Code\Mapper $folderCodeMapper */
        $folderCodeMapper = $this->createMock(Folder\Code\Mapper::class);

        $folderCodes = [
            '4' => ['ABC'],
        ];

        $folderCodeMapper->method('findForCategory')
                         ->willReturnCallback(static function (int $folderId) use ($folderCodes): GenericCollection {
                             return new GenericCollection(Folder\Code\Model::class, $folderCodes[$folderId] ?? []);
                         });
        return $folderCodeMapper;
    }

    /**
     * Returns a mock External_UserInterface whose methods will return hard-coded values
     *
     * @return External_UserInterface
     * @throws Framework\Exception
     */
    private function getMockExternalUserInterface(): External_UserInterface
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|External_UserInterface $externalUserInterface */
        $externalUserInterface = $this->createMock(External_UserInterface::class);

        $subjectLists = [
            // TODO - indexed by user.id
        ];

        $externalUserInterface->method('getSubjectList')
                              ->willReturnCallback(static function (User $user) use ($subjectLists): array {
                                  return $subjectLists[$user->getId()] ?? [];
                              });
        return $externalUserInterface;
    }

    /**
     * Returns a mock IConfigLoader whose methods will return hard-coded values
     *
     * @param bool $allowStudentClassAccess
     * @return IConfigLoader
     * @throws Framework\Exception
     */
    private function getMockIConfigLoader(bool $allowStudentClassAccess): IConfigLoader
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|IConfigLoader $iConfigLoader */
        $iConfigLoader = $this->createMock(IConfigLoader::class);

        $config = [
            'allow_student_class_access' => $allowStudentClassAccess,
        ];

        $iConfigLoader->method('get')
                      ->willReturnCallback(static function (string $name) use ($config): bool {
                          return $config[$name];
                      });
        return $iConfigLoader;
    }

    /**
     * Returns a mock RoleAccessMapper whose methods will return hard-coded values
     *
     * @param ObjectTree              $folderTree
     * @param GenericCollection<Role> $roles
     * @return RoleAccessMapper
     * @throws Framework\Exception
     */
    private function getMockRoleAccessMapper(ObjectTree $folderTree, GenericCollection $roles): RoleAccessMapper
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|RoleAccessMapper $roleAccessMapper */
        $roleAccessMapper = $this->createMock(RoleAccessMapper::class);

        /** @var Folder\Model $folder2 */
        $folder2 = $folderTree->getElementByHash(2);
        /** @var Folder\Model $folder4 */
        $folder4 = $folderTree->getElementByHash(4);

        $roleAccess = [
            0 => [],
            1 => [],
            2 => [
                1 => (new RoleAccess())->setCategory($folder2)->setRole($roles->getOffset(1))->setType(UserAccess::ACCESS_DENY),
                2 => (new RoleAccess())->setCategory($folder2)->setRole($roles->getOffset(2))->setType(UserAccess::ACCESS_READ),
                3 => (new RoleAccess())->setCategory($folder2)->setRole($roles->getOffset(3))->setType(UserAccess::ACCESS_WRITE),
                4 => (new RoleAccess())->setCategory($folder2)->setRole($roles->getOffset(4))->setType(UserAccess::ACCESS_DENY),
            ],
            3 => [],
            4 => [
                1 => (new RoleAccess())->setCategory($folder4)->setRole($roles->getOffset(1))->setType(UserAccess::ACCESS_WRITE),
                2 => (new RoleAccess())->setCategory($folder4)->setRole($roles->getOffset(2))->setType(UserAccess::ACCESS_DENY),
                3 => (new RoleAccess())->setCategory($folder4)->setRole($roles->getOffset(3))->setType(UserAccess::ACCESS_READ),
                4 => (new RoleAccess())->setCategory($folder4)->setRole($roles->getOffset(4))->setType(UserAccess::ACCESS_DENY),
            ],
            5 => [],
        ];

        $roleAccessMapper->method('findByColumnList')
                         ->willReturnCallback(static function (array $columnList) use ($roleAccess): GenericCollection {
                             $folderId = $columnList['cid'];
                             $roleId   = $columnList['gid'];
                             return new GenericCollection(RoleAccess::class, $roleAccess[$folderId][$roleId]);
                         });

        return $roleAccessMapper;
    }
}
