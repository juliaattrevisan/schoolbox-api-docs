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
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\Learning\Badge\Category\Model as Category;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from an array to model representation of badges.
 *
 * @covers \Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields
 */
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

        // Should ID be settable by this?
        yield 'id + body' => [
            [
                'id'   => 3,
                'body' => 'something',
            ],
            (new Model())->setId(3)->setBody('something'),
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
                'image'  => [
                    'hash' => '1234567890123456789012345678901234567890',
                ],
            ],
            (new Model())
                ->setImage((new Storage())->setHash('1234567890123456789012345678901234567890')),
        ];

        yield 'users' => [
            [
                'createdBy' => ['id' => 55],
            ],
            (new Model())
                ->setCreatedBy((new User())->setId(55)),
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

        // Name is not nullable:
        // that will be covered in `testInvokeUpdateInvalidValues`
        yield 'update name' => [
            ['name' => 'new name',],
            (clone $baseModel),
            (clone $baseModel)->setName('new name'),
        ];

        // Body is mutable but not nullable
        // this error case is covered in `testInvokeUpdateInvalidValues`.
        yield 'update body' => [
            ['body' => 'new body',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        // Category (not nullable)
        yield 'update category' => [
            ['category' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCategory((new Category())->setId($newId)),
        ];

        yield 'null category' => [
            ['category' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCategory((new Category())->setId(null)),
        ];

        // Body is not nullable: cover in `testInvokeUpdateInvalidValues`
//        yield 'null body' => [
//            ['body' => null,],
//            (clone $baseModel),
//            (clone $baseModel),
//        ];

        yield 'update created by' => [
            ['createdBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCreatedBy((new User())->setId($newId)),
        ];

        // Creator cannot be set to null once created:
        // this error case is covered in `testInvokeUpdateInvalidValues`.
//        yield 'null creator' => [
//            ['creator' => null,],
//            (clone $baseModel),
//            (clone $baseModel)
//        ];

        yield 'null created by' => [
            ['createdBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCreatedBy((new User())->setId(null)),
        ];

        yield 'update updated by' => [
            ['updatedBy' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedBy((new User())->setId($newId)),
        ];

        // Updater cannot be set to null once created:
        // this error case is covered in `testInvokeUpdateInvalidValues`.
//        yield 'null updatedBy by' => [
//            ['creator' => null,],
//            (clone $baseModel),
//            (clone $baseModel)
//        ];

        yield 'null updated by' => [
            ['updatedBy' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedBy((new User())->setId(null)),
        ];

        // File is mutable, by either a file upload or a storage+filename
        // Will need to mock the collector factory though
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

        // Image is not nullable::cover in invalidValues test
//        yield 'null image' => [
//            [
//                'image' => null,
//            ],
//            (clone $baseModel),
//            (clone $baseModel)->setImage(null),
//        ];

        yield 'update image from hash' => [
            [
                'image' => ['hash' => '12345'],
            ],
            (clone $baseModel),
            (clone $baseModel)->setImage((new Storage())->setHash('12345')),
            new AllFields($collectorFactoryMock),
        ];

        // Image is not null: cover in invalidValues test
//        yield 'null image' => [
//            [
//                'image' => null,
//            ],
//            (clone $baseModel),
//            (clone $baseModel)->setImage(null),
//        ];
//
//        yield 'null image hash' => [
//            [
//                'image' => ['hash' => null],
//            ],
//            (clone $baseModel),
//            (clone $baseModel)->setImage(null),
//        ];
    }


    /**
     * Data provider for `testInvokeUpdateInvalidValues`.
     *
     * @return iterable
     */
    public function getTestInvokeUpdateInvalidValuesData(): iterable
    {
        $baseModel = (new Model())
            ->setCreator((new User())->setId(1))
            ->setRecipient((new User())->setId(1));

        // Creator cannot be set to null once created: this should be an error
        yield 'null creator' => [
            ['creator' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        // Likewise recipient can't be set to null once created
        // This should be an error
        yield 'null recipient' => [
            ['recipient' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];
    }


    /**
     * Test that creation of a new model from an array works as expected.
     *
     * @dataProvider getTestInvokeCreateData
     * @covers       \Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields::__invoke
     *
     * @param array          $input
     * @param Model          $expected
     * @param AllFields|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    public function testInvokeCreate(array $input, Model $expected, AllFields $converter = null): void
    {
        $converter = $this->createConverterIfNull($converter);

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input));
    }


    /**
     * Test that update of an existing model from an array works as expected.
     *
     * @dataProvider getTestInvokeUpdateData
     * @covers       \Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields::__invoke
     *
     * @param array          $input
     * @param Model          $existing
     * @param Model          $expected
     * @param AllFields|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
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
     * @dataProvider getTestInvokeUpdateInvalidValuesData
     * @covers       \Schoolbox\Learning\Badge\Translator\ArrayToModel\AllFields::__invoke
     *
     * @param array $input
     * @param Model $existing
     * @param Model $expected
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    public function testInvokeUpdateInvalidValues(array $input, Model $existing, Model $expected): void
    {
        $converter = $this->createConverterIfNull();

        $this->expectWarning();

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input, $existing));
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
            $converter            ??= new AllFields($collectorFactoryMock);
        }
        return $converter;
    }
}
