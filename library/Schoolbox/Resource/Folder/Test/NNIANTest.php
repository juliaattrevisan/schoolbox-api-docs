<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Test;

use InvalidArgumentException;
use PHPUnit\Framework;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use ReflectionClass;
use ReflectionException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;

/**
 * Tests the NNIAN class.
 *
 * Folder Tree:
 * id=0
 * └-- id=1, inherit=0
 *     |-- id=2, inherit=0
 *     |   └-- id=4, inherit=0
 *     |       └-- id=5, inherit=1
 *     |          └-- id=6, inherit=1
 *     └-- id=3, inherit=1
 *
 * @codeCoverageIgnore
 * @covers \Schoolbox\Resource\Folder\NNIAN
 */
class NNIANTest extends TestCase
{
    // ===================================== Hard-coded state =====================================

    private const FOLDER_DATA = [
        // id, parent, inherit
        [1, Folder\Model::ROOT_ID, false],
        [2, 1, false],
        [3, 1, true],
        [4, 2, false],
        [5, 4, true],
        [6, 5, true],
    ];


    // ===================================== Tests =====================================


    /**
     * Tests that testing a folder stub model throws expected exception
     *
     * @throws Framework\Exception
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\NotFound
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @expectedException InsufficientHydrationException
     */
    public function testInsufficientHydration(): void
    {
        $this->expectException(InsufficientHydrationException::class);
        $this->newNnian()->findNearestNonInheritingAncestralNode(new Folder\Model());
    }


    /**
     * Tests that testing the folder pseudo root throws expected exception
     *
     * @throws Framework\Exception
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\NotFound
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     * @expectedException InvalidArgumentException
     */
    public function testFindNearestNonInheritingAncestralNodeRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newNnian()->findNearestNonInheritingAncestralNode((new Folder\Model())->setId(Folder\Model::ROOT_ID));
    }


    /**
     * Tests that testing the folder pseudo root throws expected exception
     *
     * @throws Framework\Exception
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\NotFound
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     * @expectedException InvalidArgumentException
     */
    public function testGetNnianAncestorsRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newNnian()->getNnianAncestors((new Folder\Model())->setId(Folder\Model::ROOT_ID));
    }


    /**
     * Tests that an invalid top-level folder throws expected exception
     *
     * @throws Framework\Exception
     * @throws Mapper\DefinitionException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @expectedException Mapper\Exception
     */
    public function testGetNnianAncestorsTopLevelInherit(): void
    {
        $folderData = [[1, Folder\Model::ROOT_ID, true]];
        $folders    = $this->getFolders($folderData);

        $this->resetNnianInstance();
        $nnian = Folder\NNIAN::getInstance($this->getMockFolderMapper($folders, true));

        $this->expectException(Mapper\Exception::class);
        $nnian->getNnianAncestors($folders->getOffset(1));
    }


    /**
     * Tests that circular inheritance throws expected exception
     *
     * @throws Framework\Exception
     * @throws Mapper\DefinitionException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @expectedException Mapper\Exception
     */
    public function testGetNnianAncestorsCircular(): void
    {
        $folderData = [
            [2, 3, true],
            [3, 2, true],
        ];
        $folders    = $this->getFolders($folderData);

        $this->resetNnianInstance();
        $nnian = Folder\NNIAN::getInstance($this->getMockFolderMapper($folders, true));

        $this->expectException(Mapper\Exception::class);
        $nnian->getNnianAncestors($folders->getOffset(2));
    }


    /**
     * Test non-inheriting folders
     *
     * @dataProvider providerNnianAncestorsNotInheriting
     * @param Folder\NNIAN $nnian
     * @param Folder\Model $nonInheritingFolder
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws Mapper\Exception\NotFound
     * @throws UnexpectedValueException
     */
    public function testGetNnianAncestorsNotInheriting(Folder\NNIAN $nnian, Folder\Model $nonInheritingFolder): void
    {
        $this->assertEquals(
            $nonInheritingFolder->getId(),
            $nnian->findNearestNonInheritingAncestralNode($nonInheritingFolder)->getId()
        );
    }


    /**
     * Test inheriting folders
     *
     * @dataProvider providerNnianAncestorsInheriting
     * @param Folder\NNIAN $nnian
     * @param Folder\Model $inheritingFolder
     * @param Folder\Model $expectedNnian
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws MapperException\NotFound
     * @throws Mapper\DefinitionException
     * @throws UnexpectedValueException
     */
    public function testGetNnianAncestorsInheriting(
        Folder\NNIAN $nnian,
        Folder\Model $inheritingFolder,
        Folder\Model $expectedNnian
    ): void {
        $this->assertEquals(
            $expectedNnian->getId(),
            $nnian->findNearestNonInheritingAncestralNode($inheritingFolder)->getId()
        );
    }


    /**
     * Tests a folder that does not have parent hydrated can be hydrated on the fly
     *
     * @dataProvider providerNnianAncestorsNotInheriting
     * @param Folder\NNIAN $nnian
     * @param Folder\Model $notInheritingFolder
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws MapperException\NotFound
     * @throws Mapper\DefinitionException
     * @throws UnexpectedValueException
     */
    public function testFindNearestNonInheritingAncestralNodeParentNotHydrated(
        Folder\NNIAN $nnian,
        Folder\Model $notInheritingFolder
    ): void {
        $stub = (new Folder\Model)->setId($notInheritingFolder->getId());
        $this->assertEquals(
            $stub->getId(),
            $nnian->findNearestNonInheritingAncestralNode($stub)->getId()
        );
    }


    /**
     * Tests that the code throws an appropriate exception if a circular inheritance graph is encountered.
     *
     * @dataProvider providerDetectCycle
     * @param bool $beforeCycle Should we test a node prior to cycle, or inside cycle?
     * @param int  $n           Number of nodes in cycle. Must be >= 2
     * @throws Framework\Exception
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws ReflectionException
     * @throws UnexpectedValueException
     * @expectedException MapperException
     */
    public function testDetectCycle(bool $beforeCycle, int $n): void
    {
        $this->expectException(MapperException::class);

        // Create a loop of N folders, eg, 1 > 2 > 3 > 4 ...N > 2 > 3 ...
        // Note here that 1 is not part of the loop, 2 IS part of the loop

        $leaf       = (new Folder\Model())
            ->setId(1)
            ->setName("Folder 1")
            ->setInherit(true);
        $prevFolder = $leaf;

        // Build a straight branch
        for ($i = 2; $i <= $n; $i++) {
            $prevFolder->setParent(
                (new Folder\Model())
                    ->setId($i)
                    ->setName("Folder $i")
                    ->setInherit(true)
            );
            $prevFolder = $prevFolder->getParent();
        }

        // Now loop the branch
        $prevFolder->setParent($leaf->getParent());

        $nnian = $this->newNnian([], false);

        $nnian->findNearestNonInheritingAncestralNode($beforeCycle ? $leaf : $leaf->getParent());
    }


    // ===================================== Data Providers =====================================


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerNnianAncestorsNotInheriting(): array
    {
        $nnian   = $this->newNnian();
        $folders = $this->getFolders();
        return [
            // Double tap first test to invoke cache
            [$nnian, $folders->getOffset(1)],
            [$nnian, $folders->getOffset(1)],
            [$nnian, $folders->getOffset(2)],
            [$nnian, $folders->getOffset(4)],
        ];
    }


    /**
     * @return array
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function providerNnianAncestorsInheriting(): array
    {
        $nnian   = $this->newNnian();
        $folders = $this->getFolders();
        return [
            // Double tap first test to invoke cache
            [$nnian, $folders->getOffset(3), $folders->getOffset(1)],
            [$nnian, $folders->getOffset(3), $folders->getOffset(1)],
            [$nnian, $folders->getOffset(5), $folders->getOffset(4)],
            [$nnian, $folders->getOffset(6), $folders->getOffset(4)],
        ];
    }


    /**
     * @return array
     */
    public function providerDetectCycle(): array
    {
        // bool $beforeCycle, int $n
        return [
            [true, 2],
            [false, 2],
            [true, 10],
            [false, 10],
            [true, 1000],
            [false, 1000],
        ];
    }

    // ===================================== Helpers =====================================


    /**
     * @param array|null $folderData
     * @param bool|null  $isCacheEnabled
     * @return Folder\NNIAN
     * @throws Framework\Exception
     * @throws ReflectionException
     * @throws UnexpectedValueException
     */
    public function newNnian(array $folderData = null, bool $isCacheEnabled = null): Folder\NNIAN
    {
        $this->resetNnianInstance();

        return Folder\NNIAN::getInstance(
            $this->getMockFolderMapper($this->getFolders($folderData), $isCacheEnabled ?? true)
        );
    }


    /**
     * Resets the NNIAN singleton instance
     *
     * @throws ReflectionException
     */
    private function resetNnianInstance(): void
    {
        $reflection = new ReflectionClass(Folder\NNIAN::class);
        $instance   = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $instance->setAccessible(false);
    }


    /**
     * @param array $folderData
     * @return GenericCollection|Folder\Model[]
     * @throws UnexpectedValueException
     */
    private function getFolders(array $folderData = null): GenericCollection
    {
        $folderData = $folderData ?? self::FOLDER_DATA;
        $folders    = Folder\Model::createCollection();
        $folders->setOffset(Folder\Model::ROOT_ID, (new Folder\Model())->setId(Folder\Model::ROOT_ID));
        foreach ($folderData as [$id, $parentId, $inherit]) {
            $folders->setOffset(
                $id,
                (new Folder\Model())
                    ->setId($id)
                    ->setInherit($inherit)
            );
        }
        foreach ($folderData as [$id, $parentId, $inherit]) {
            /** @var Folder\Model $folder */
            $folder = $folders->getOffset($id);
            $folder->setParent($folders->getOffset($parentId));
        }
        return $folders;
    }


    /**
     * Returns a mock Folder Mapper whose methods will simulate a specific folder tree
     *
     * @param GenericCollection|Folder\Model[] $folders
     * @param bool                             $isCacheEnabled
     * @return Folder\Mapper
     * @throws Framework\Exception
     */
    private function getMockFolderMapper(GenericCollection $folders, bool $isCacheEnabled): Folder\Mapper
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|Folder\Mapper $folderMapper */
        $folderMapper = $this->createMock(Folder\Mapper::class);

        /** @see \Schoolbox\Resource\Folder\Mapper::findCheaplyById */
        $folderMapper->method('findCheaplyById')
                     ->willReturnCallback(static function (int $folderId) use ($folders): Folder\Model {
                         return $folders->getOffset($folderId);
                     });

        /** @see \Schoolbox\Resource\Folder\Mapper::isCacheEnabled() */
        $folderMapper->method('isCacheEnabled')
                     ->willReturnCallback(static function () use ($isCacheEnabled): bool {
                         return $isCacheEnabled;
                     });

        return $folderMapper;
    }
}
