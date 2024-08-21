<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ArrayToModel\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Badge\Category\Model as Category;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;
use TypeError;

/**
 * Tests for converting from an array to model representation of badges.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields::class)]
class AllFieldsTest extends TestCase
{
    /**
     * Data provider for `testInvokeCreate`.
     *
     * @return iterable
     */
    public function getTestInvokeCreateData(): iterable
    {
        yield 'empty' => [[], new Model()];

        yield 'id + name + body' => [
            [
                'id'   => 3,
                'name' => 'test',
                'body' => 'something',
            ],
            (new Model())
                ->setId(3)
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
                ->setId(3)
                ->setName('test')
                ->setBody('something'),
        ];

        $timezone = new DateTimeZone('+00:00');
        yield 'dates' => [
            [
                'createdAt' => '2020-01-01T00:00:00+00:00',
                'updatedAt' => '2020-02-01T00:00:00+00:00',
                'deletedAt' => '2020-03-01T00:00:00+00:00',
            ],
            (new Model())
                ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setDeletedAt(new Alaress_Date_Format('2020-03-01T00:00:00+00:00', DATE_ATOM, $timezone)),
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
            (new Model())
                ->setCreatedBy((new User())->setId(55))
                ->setUpdatedBy((new User())->setId(55)),
        ];

        // Storage file upload: require a mock here
        $storage       = (new Storage())
            ->setHash('1234567890123456789012345678901234567890')
            ->setBasename('test.gif');
        $collectorMock = $this->createMock(Collector::class);
        $collectorMock->method('getFirst')->willReturn($storage);

        /** @var CollectorFactory|MockObject $collectorFactoryMock */
        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
        $collectorFactoryMock->method('build')->willReturn($collectorMock);

        yield 'image as file upload' => [
            [
                'image' => 'file:1234567890123456789012345678901234567890:test.gif',
            ],
            (new Model())
                ->setImage($storage),
            new AllFields($collectorFactoryMock),
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
    public function getTestInvokeUpdateData(): iterable
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
            ->setAchievementExpiry(Model::EXPIRY_DAY)
            ->setGoalExpiry(Model::EXPIRY_DAY)
            ->setCreatedBy((new User())->setId($oldId))
            ->setUpdatedBy((new User())->setId($oldId));


        yield 'update id' => [
            ['id' => $newId,],
            (clone $baseModel),
            (clone $baseModel)->setId($newId),
        ];

        yield 'null id' => [
            ['id' => null,],
            (clone $baseModel),
            (clone $baseModel)->setId(null),
        ];

        yield 'update created at' => [
            ['createdAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(new Alaress_Date_Format($newDate, DATE_ATOM, $timezone)),
        ];

        yield 'null created at' => [
            ['createdAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(null),
        ];

        yield 'update updated at' => [
            ['updatedAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedAt(new Alaress_Date_Format($newDate, DATE_ATOM, $timezone)),
        ];

        yield 'null updated at' => [
            ['updatedAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedAt(null),
        ];

        yield 'update deleted at' => [
            ['deletedAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel)->setDeletedAt(new Alaress_Date_Format($newDate, DATE_ATOM, $timezone)),
        ];

        yield 'null deleted at' => [
            ['deletedAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setDeletedAt(null),
        ];

        // Name is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
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

        // Body is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
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

        // Category is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
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

        // Created by is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
        yield 'update created by' => [
            ['createdBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCreatedBy((new User())->setId($newId)),
        ];

        yield 'null created by id' => [
            ['createdBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCreatedBy((new User())->setId(null)),
        ];

        // Updated by is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
        yield 'update updated by' => [
            ['updatedBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedBy((new User())->setId($newId)),
        ];

        yield 'null updated by id' => [
            ['updatedBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedBy((new User())->setId(null)),
        ];

        // Image set from upload and storage hash
        // Image is not nullable
        // (covered in `testInvokeUpdateInvalidValues)
        $storage       = (new Storage())
            ->setHash('9876543210987654321098765432109876543210')
            ->setBasename('test.png');
        $collectorMock = $this->createMock(Collector::class);
        $collectorMock->method('getFirst')->willReturn($storage);

        /** @var CollectorFactory|MockObject $collectorFactoryMock */
        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
        $collectorFactoryMock->method('build')->willReturn($collectorMock);

        yield 'update image from upload' => [
            [
                'image' => 'file:9876543210987654321098765432109876543210:test.png',
            ],
            (clone $baseModel),
            (clone $baseModel)->setImage($storage),
            new AllFields($collectorFactoryMock),
        ];

        yield 'update image from hash' => [
            [
                'image' => ['hash' => '12345'],
            ],
            (clone $baseModel),
            (clone $baseModel)->setImage((new Storage())->setHash('12345')),
            new AllFields($collectorFactoryMock),
        ];
    }


    /**
     * Data provider for `testInvokeUpdateInvalidValues`.
     *
     * @return iterable
     */
    public static function getTestInvokeUpdateInvalidValuesData(): iterable
    {
        $oldDate   = '2020-01-01T00:00:00+00:00';
        $oldId     = 1;
        $timezone  = new DateTimeZone('+00:00');
        $baseModel = (new Model())
            ->setId($oldId)
            ->setCreatedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setUpdatedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setDeletedAt(new Alaress_Date_Format($oldDate, DATE_ATOM, $timezone))
            ->setName('test')
            ->setBody('test')
            ->setCategory((new Category())->setId($oldId))
            ->setCreatedBy((new User())->setId($oldId))
            ->setUpdatedBy((new User())->setId($oldId));

        // These fields are not nullable:
        // * name
        // * body
        // * category
        // * createdBy
        // * updatedBy

        yield 'null name' => [
            ['name' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null body' => [
            ['body' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null category' => [
            ['category' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null created by' => [
            ['createdBy' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null updated by' => [
            ['updatedBy' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];
    }

    /**
     * Data provider for `testInvokeInvalidAchievementExpiry`.
     *
     * @return iterable
     */
    public static function getTestInvokeInvalidAchievementExpiryData(): iterable
    {
        return [
            '0 days' => [0],
            '2 days' => [2],
            '-1 day' => [-1],
            '366 days' => [366],
        ];
    }


    /**
     * Test that creation of a new model from an array works as expected.
     *
     *
     * @param array          $input
     * @param Model          $expected
     * @param AllFields|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeCreateData')]
    public function testInvokeCreate(array $input, Model $expected, AllFields $converter = null): void
    {
        $converter = $this->createConverterIfNull($converter);

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input));
    }


    /**
     * Test that update of an existing model from an array works as expected.
     *
     *
     * @param array          $input
     * @param Model          $existing
     * @param Model          $expected
     * @param AllFields|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeUpdateData')]
    public function testInvokeUpdate(
        array $input,
        Model $existing,
        Model $expected,
        AllFields $converter = null
    ): void {
        $converter = $this->createConverterIfNull($converter);

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input, $existing));
    }


    /**
     * Test that attempting to update an existing model from an array refuses to
     * set fields which would cause an error: these fields should raise a
     * warning message, and leave the value as is.
     *
     *
     * @param array $input
     * @param Model $existing
     * @param Model $expected
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeUpdateInvalidValuesData')]
    public function testInvokeUpdateInvalidValues(array $input, Model $existing, Model $expected): void
    {
        $this->expectException(TypeError::class);
        $converter = $this->createConverterIfNull();

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input, $existing));
    }


    /**
     * Test that setting an invalid achievement expiry value raises an error.
     *
     * This only tests int values: `null` is a valid value, and other non-int
     * values are covered by type definitions.
     *
     *
     * @param int $invalidValue
     * @noinspection PhpDocMissingThrowsInspection We know this will be thrown, and catch it
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeInvalidAchievementExpiryData')]
    public function testInvokeInvalidAchievementExpiry(int $invalidValue): void
    {
        $converter = $this->createConverterIfNull();

        $this->expectException(\UnexpectedValueException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $converter(['achievementExpiry' => $invalidValue,], new Model());
    }


    /**
     * Test that setting an invalid goal expiry value raises an error.
     *
     * The invalid values for goal expiry are the same as achievement expiry, so
     * the data provider is the same.
     *
     *
     * @param int $invalidValue
     * @noinspection PhpDocMissingThrowsInspection We know this will be thrown, and catch it
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeInvalidAchievementExpiryData')]
    public function testInvokeInvalidGoalExpiry(int $invalidValue): void
    {
        $converter = $this->createConverterIfNull();

        $this->expectException(\UnexpectedValueException::class);

        /** @noinspection PhpUnhandledExceptionInspection */
        $converter(['goalExpiry' => $invalidValue,], new Model());
    }


    /**
     * Helper for creating the converter if it is null.
     *
     * If it's null, it creates a converter which contains a mock of the storage
     * object collector factory.
     *
     * @param AllFields|null $converter
     * @return AllFields
     */
    private function createConverterIfNull(?AllFields $converter = null): AllFields
    {
        if (!$converter) {
            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $converter            = new AllFields($collectorFactoryMock);
        }
        return $converter;
    }
}
