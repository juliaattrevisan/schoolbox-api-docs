<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Badge\Category\Model as Category;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Learning\Badge\Translator\ArrayToModel\MutableFieldsOnly;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from an array to model representation of badges, when
 * only the subset of fields which is mutable by API calls is converted.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Badge\Translator\ArrayToModel\MutableFieldsOnly::class)]
class MutableFieldsOnlyTest extends TestCase
{
    /**
     * Data provider for `testInvokeCreate`.
     *
     * @return iterable
     */
    public static function getTestInvokeCreateData(): iterable
    {
        yield 'empty' => [[], new Model()];

        yield 'id + name + body' => [
            [
                'id'   => 3,
                'name' => 'test',
                'body' => 'something',
            ],
            (new Model())
                ->setName('test')
                ->setBody('something'),
        ];

        yield 'id + name + body: trim' => [
            [
                'id'   => 3,
                'name' => '  test  ',
                'body' => '  something  ',
            ],
            (new Model())
                ->setName('test')
                ->setBody('something'),
        ];

        yield 'dates' => [
            [
                'createdAt' => '2020-01-01T00:00:00+00:00',
                'updatedAt' => '2020-02-01T00:00:00+00:00',
                'deletedAt' => '2020-03-01T00:00:00+00:00',
            ],
            (new Model()),
        ];

        yield 'image as hash' => [
            [
                'image' => [
                    'hash' => '1234567890123456789012345678901234567890',
                ],
            ],
            (new Model())
                ->setImage((new Storage())->setHash('1234567890123456789012345678901234567890')),
        ];

        yield 'users' => [
            [
                'createdBy' => ['id' => 55],
                'updatedBy' => ['id' => 55],
            ],
            (new Model()),
        ];

        // Storage file upload: require a mock here
        $storage = (new Storage())
            ->setHash('1234567890123456789012345678901234567890')
            ->setBasename('test.gif');

        yield 'image as file upload' => [
            [
                'image' => 'file:1234567890123456789012345678901234567890:test.gif',
            ],
            (new Model())->setImage($storage),
            $storage,
        ];

        yield 'achievement expiry' => [
            [
                'achievementExpiry' => Model::EXPIRY_DAY,
            ],
            (new Model())
                ->setAchievementExpiry(Model::EXPIRY_DAY),
        ];

        yield 'goal expiry' => [
            [
                'goalExpiry' => Model::EXPIRY_DAY,
            ],
            (new Model())
                ->setGoalExpiry(Model::EXPIRY_DAY),
        ];
    }


    /**
     * Data provider for `testInvokeUpdate`.
     *
     * @return iterable
     */
    public static function getTestInvokeUpdateData(): iterable
    {
        $oldDate   = '2020-01-01T00:00:00+00:00';
        $newDate   = '2020-02-01T00:00:00+00:00';
        $oldId     = 1;
        $newId     = 2;
        $timezone  = new DateTimeZone('+00:00');
        $baseModel = (new Model())
            ->setId($oldId)
            ->setCreatedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setUpdatedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setDeletedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setName('test')
            ->setBody('test')
            ->setImage((new Storage())->setHash('1234567890123456789012345678901234567890'))
            ->setCategory((new Category())->setId($oldId))
            ->setCreatedBy((new User())->setId($oldId))
            ->setUpdatedBy((new User())->setId($oldId));


        yield 'update id' => [
            ['id' => $newId,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null id' => [
            ['id' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'update created at' => [
            ['createdAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null created at' => [
            ['createdAt' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'update updated at' => [
            ['updatedAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null updated at' => [
            ['updatedAt' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'update deleted at' => [
            ['deletedAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null deleted at' => [
            ['deletedAt' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'update name' => [
            ['name' => 'new name',],
            (clone $baseModel),
            (clone $baseModel)->setName('new name'),
        ];

        yield 'update name - left trim' => [
            ['name' => '  new name',],
            (clone $baseModel),
            (clone $baseModel)->setName('new name'),
        ];

        yield 'update name - right trim' => [
            ['name' => 'new name  ',],
            (clone $baseModel),
            (clone $baseModel)->setName('new name'),
        ];

        yield 'update name - left+right trim' => [
            ['name' => '  new name  ',],
            (clone $baseModel),
            (clone $baseModel)->setName('new name'),
        ];

        yield 'update body' => [
            ['body' => 'new body',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        yield 'update body - left trim' => [
            ['body' => ' new body',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        yield 'update body - right trim' => [
            ['body' => 'new body ',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        yield 'update body - left+right trim' => [
            ['body' => ' new body ',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        yield 'update category' => [
            ['category' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCategory((new Category())->setId($newId)),
        ];

        yield 'null category id' => [
            ['category' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCategory((new Category())->setId(null)),
        ];

        yield 'update achievement expiry' => [
            ['achievementExpiry' => Model::EXPIRY_MONTH,],
            (clone $baseModel),
            (clone $baseModel)->setAchievementExpiry(Model::EXPIRY_MONTH),
        ];

        yield 'null achievement expiry' => [
            ['achievementExpiry' => null,],
            (clone $baseModel),
            (clone $baseModel)->setAchievementExpiry(null),
        ];

        yield 'update goal expiry' => [
            ['goalExpiry' => Model::EXPIRY_MONTH,],
            (clone $baseModel),
            (clone $baseModel)->setGoalExpiry(Model::EXPIRY_MONTH),
        ];

        yield 'null goal expiry' => [
            ['goalExpiry' => null,],
            (clone $baseModel),
            (clone $baseModel)->setGoalExpiry(null),
        ];

        yield 'update created by' => [
            ['createdBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null created by id' => [
            ['createdBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        // Updated by is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
        yield 'update updated by' => [
            ['updatedBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null updated by id' => [
            ['updatedBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        // Image set from upload and storage hash
        // Image is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
        $storage1 = (new Storage())
            ->setHash('9876543210987654321098765432109876543210')
            ->setBasename('test.png');

        yield 'update image from upload' => [
            [
                'image' => 'file:9876543210987654321098765432109876543210:test.png',
            ],
            (clone $baseModel),
            (clone $baseModel)->setImage($storage1),
            $storage1,
        ];

        $storage2 = (new Storage())->setHash('12345');
        yield 'update image from hash' => [
            [
                'image' => ['hash' => '12345'],
            ],
            (clone $baseModel),
            (clone $baseModel)->setImage($storage2),
            $storage2,
        ];
    }


    /**
     * Test that creation of a new model from an array works as expected.
     *
     *
     * @param array         $input
     * @param Model         $expected
     * @param IStorage|null $storage
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeCreateData')]
    public function testInvokeCreate(array $input, Model $expected, ?Istorage $storage = null): void
    {
        $converter = $this->createConverter($storage);

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input));
    }


    /**
     * Test that update of an existing model from an array works as expected.
     *
     *
     * @param array         $input
     * @param Model         $existing
     * @param Model         $expected
     * @param IStorage|null $storage
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeUpdateData')]
    public function testInvokeUpdate(
        array $input,
        Model $existing,
        Model $expected,
        ?Istorage $storage = null
    ): void {
        $converter = $this->createConverter($storage);

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input, $existing));
    }


    /**
     * Helper for creating the converter if it is null.
     *
     * If it's null, it creates a converter which contains a mock of the storage
     * object collector factory.
     *
     * @param IStorage|null $storage
     * @return MutableFieldsOnly
     * @throws Exception
     */
    private function createConverter(?IStorage $storage = null): MutableFieldsOnly
    {
        if ($storage) {
            $collectorMock = $this->createMock(Collector::class);
            $collectorMock->method('getFirst')->willReturn($storage);

            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $collectorFactoryMock->method('build')->willReturn($collectorMock);
            return new MutableFieldsOnly($collectorFactoryMock);
        }

        /** @var CollectorFactory|MockObject $collectorFactoryMock */
        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
        return new MutableFieldsOnly($collectorFactoryMock);
    }
}
