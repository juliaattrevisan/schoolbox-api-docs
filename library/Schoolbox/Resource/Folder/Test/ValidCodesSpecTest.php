<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Schoolbox\Resource\Folder\ValidCodesSpec;
use Schoolbox\Resource\Folder;
use PHPUnit\Framework;
use Alaress\Database\Predicate;
use Alaress\Database\Quoter\Mysql;
use Schoolbox\Test\MockDatabase;

/**
 * @covers \Schoolbox\Resource\Folder\ValidCodesSpec
 * @codeCoverageIgnore
 */
class ValidCodesSpecTest extends TestCase
{

    // Tests

    /**
     * @param array $codes
     * @param array $otherFolderCodes
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerNonUniqueOnFolder
     */
    public function testNonUniqueOnFolder(array $codes, array $otherFolderCodes): void
    {
        $folderModel = new Folder\Model();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($otherFolderCodes)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param array $otherFolderCodes
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerUniqueOnFolder
     */
    public function testUniqueOnFolder(array $codes, array $otherFolderCodes): void
    {
        $folderModel = new Folder\Model();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertTrue($this->createSpec($otherFolderCodes)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param array $otherFolderCodes
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerExactCollisionWithOtherFolder
     */
    public function testExactCollisionWithOtherFolder(array $codes, array $otherFolderCodes): void
    {
        $folderModel = new Folder\Model();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($otherFolderCodes)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param array $otherFolderCodes
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerPrefixCollisionWithOtherFolder
     */
    public function testPrefixCollisionWithOtherFolder(array $codes, array $otherFolderCodes): void
    {
        $folderModel = new Folder\Model();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($otherFolderCodes)->isSatisfiedBy($folderModel));
    }


    /**
     * @param int|null $folderId
     * @param array    $codes
     * @param array    $otherFolderCodes
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerNoCollisionWithOtherFolder
     */
    public function testNoCollisionWithOtherFolder(?int $folderId, array $codes, array $otherFolderCodes): void
    {
        $folderModel = (new Folder\Model())->setId($folderId);

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertTrue($this->createSpec($otherFolderCodes)->isSatisfiedBy($folderModel));

    }


    // Providers

    public function providerNonUniqueOnFolder(): array
    {
        return [
            [['a', 'a'], []],
            [['a', 'a'], []],
            [['a', 'a/a'], []],
            [['a', 'a/a'], []],
            [['a', 'a a/a'], []],
            [['a', 'a a/a'], []],
        ];
    }


    public function providerUniqueOnFolder(): array
    {
        return [
            [[], []],
            [['a'], []],
            [['a/aaa', 'b/bbb'], []],
            [['a/a', 'aa', 'a a', 'a a a'], []],
        ];
    }


    public function providerExactCollisionWithOtherFolder(): array
    {
        return [
            [['a'], ['a']],
            [['a/aaa'], ['a/aaa']],
            [['a', 'b', 'c', 'd'], ['a']],
            [['a/aaa'], ['c', 'a/aaa', 'b', 'b/bbb']],
        ];
    }


    public function providerPrefixCollisionWithOtherFolder(): array
    {
        return [
            [['a'], ['S/a']],
            [['S/a'], ['a']],
        ];
    }


    public function providerNoCollisionWithOtherFolder(): array
    {
        return [
            [null, ['a'], ['b']],
            [null, ['a'], ['b']],
            [null, ['a'], ['aa']],
            [null, ['a/aaa'], ['b/aaa']],
            [null, ['a/aaa'], ['a a/aaa']],
            [null, ['a/aaa'], ['a/bbb']],
            [1, ['a'], ['b']],
            [1, ['a'], ['b']],
            [1, ['a'], ['aa']],
            [1, ['a/aaa'], ['b/aaa']],
            [1, ['a/aaa'], ['a a/aaa']],
            [1, ['a/aaa'], ['a/bbb']],
        ];
    }


    // Helpers


    /**
     * Builds a validation spec that contains a folderCode mapper that returns the specified folder codes
     *
     * @param array $otherFolderCodes
     * @return ValidCodesSpec
     * @throws Framework\Exception
     */
    private function createSpec(array $otherFolderCodes): ValidCodesSpec
    {
        /** @var Folder\Code\Mapper|PHPUnit_Framework_MockObject_MockObject $folderCodeMapper */
        $folderCodeMapper = $this->createMock(Folder\Code\Mapper::class);

        $mockDatabase = $this->createMock(MockDatabase::class);
        $mockDatabase
            ->method('escapeForLike')
            ->willReturn('mock escaped string');

        $folderCodeMapper
            ->method('getPredicateBuilder')
            ->willReturn(new Predicate\Builder(new Mysql\Quoter(), 'folder_code'));

        $folderCodeMapper
            ->method('getDatabase')
            ->willReturn($mockDatabase);

        $folderCodeMapper
            ->method('findScalarByPredicates')
            ->willReturn($otherFolderCodes);

        return new ValidCodesSpec($folderCodeMapper);
    }
}
