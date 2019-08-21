<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
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
     * @param int   $countDbMatches
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerNonUniqueOnFolder
     */
    public function testNonUniqueOnFolder(array $codes, int $countDbMatches): void
    {
        $folderModel = $this->createFolderWithEmptyCodes();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($countDbMatches)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param int   $countDbMatches
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerUniqueOnFolder
     */
    public function testUniqueOnFolder(array $codes, int $countDbMatches): void
    {
        $folderModel = $this->createFolderWithEmptyCodes();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertTrue($this->createSpec($countDbMatches)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param int   $countDbMatches
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerExactCollisionWithOtherFolder
     */
    public function testExactCollisionWithOtherFolder(array $codes, int $countDbMatches): void
    {
        $folderModel = $this->createFolderWithEmptyCodes();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($countDbMatches)->isSatisfiedBy($folderModel));
    }


    /**
     * @param array $codes
     * @param int   $countDbMatches
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerPrefixCollisionWithOtherFolder
     */
    public function testPrefixCollisionWithOtherFolder(array $codes, int $countDbMatches): void
    {
        $folderModel = $this->createFolderWithEmptyCodes();

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertFalse($this->createSpec($countDbMatches)->isSatisfiedBy($folderModel));
    }


    /**
     * @param int|null $folderId
     * @param array    $codes
     * @param int      $countDbMatches
     * @throws Framework\AssertionFailedError
     * @throws Framework\Exception
     * @throws Predicate\InvalidColumnException
     * @dataProvider providerNoCollisionWithOtherFolder
     */
    public function testNoCollisionWithOtherFolder(?int $folderId, array $codes, int $countDbMatches): void
    {
        $folderModel = $this->createFolderWithEmptyCodes()->setId($folderId);

        foreach ($codes as $code) {
            $folderModel->getCodes()->add((new Folder\Code\Model())->setCode($code));
        }

        $this->assertTrue($this->createSpec($countDbMatches)->isSatisfiedBy($folderModel));

    }


    // Providers

    public function providerNonUniqueOnFolder(): array
    {
        return [
            [['a', 'A'], 0],
            [['a', 'A'], 0],
            [['a', 'A/A'], 0],
            [['a', 'A/A'], 0],
            [['a', 'A A/A'], 0],
            [['a', 'A A/A'], 0],
            [['1', '1'], 0],
            [['a&a', 'A&A'], 0],
        ];
    }


    public function providerUniqueOnFolder(): array
    {
        return [
            [[], 0],
            [['a'], 0],
            [['a/aaa', 'b/bbb'], 0],
            [['a/a', 'aa', 'a a', 'a a a'], 0],
            [['1', '2', '3'], 0],
            [['a&a', 'a& a'], 0],
        ];
    }


    public function providerExactCollisionWithOtherFolder(): array
    {
        return [
            [['a'], 1],
            [['1'], 1],
            [['&'], 1],
        ];
    }


    public function providerPrefixCollisionWithOtherFolder(): array
    {
        return [
            [['a'], 1],
            [['S/a'], 1],
            [['S/1'], 1],
            [['S/&'], 1],
        ];
    }


    public function providerNoCollisionWithOtherFolder(): array
    {
        return [
            [null, ['a'], 0],
            [null, ['a/aaa'], 0],
            [null, ['1'], 0],
            [null, ['a&a'], 0],
            [1, ['a'], 0],
            [1, ['a/aaa'], 0],
            [1, ['1'], 0],
            [1, ['a&a'], 0],
        ];
    }


    // Helpers


    /**
     * Builds a validation spec that contains a folderCode mapper that will pretend
     * to return the specified number of matches.
     *
     * @param int $countDbMatches
     * @return ValidCodesSpec
     * @throws Framework\Exception
     */
    private function createSpec(int $countDbMatches): ValidCodesSpec
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

        $results = new Collection(array_fill(0, $countDbMatches, null));
        $folderCodeMapper
            ->method('findByPredicates')
            ->willReturn($results);

        return new ValidCodesSpec($folderCodeMapper);
    }

    /**
     * @return Folder\Model
     */
    private function createFolderWithEmptyCodes(): Folder\Model
    {
        return (new Folder\Model())->setCodes(new GenericCollection(Folder\Code\Model::class));
    }
}
