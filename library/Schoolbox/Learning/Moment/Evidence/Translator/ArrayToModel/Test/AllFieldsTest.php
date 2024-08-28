<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\Test;

use Alaress_Date_Format;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\AllFields;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from an array to model representation of learning moment
 * evidence.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\AllFields::class)]
class AllFieldsTest extends TestCase
{
    /**
     * Data provider for `testInvokeCreate`.
     *
     * @return iterable
     */
    public static function getTestInvokeCreateData(): iterable
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

        yield 'storage' => [
            [
                'storage'  => [
                    'hash' => '1234567890123456789012345678901234567890',
                ],
                'filename' => 'test.gif',
            ],
            (new Model())
                ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
                ->setFilename('test.gif'),
        ];

        yield 'users' => [
            [
                'creator'   => ['id' => 55],
                'recipient' => ['id' => 193],
            ],
            (new Model())
                ->setCreator((new User())->setId(55))
                ->setRecipient((new User())->setId(193)),
        ];

        yield 'discussion thread' => [
            [
                'discussionThread' => ['id' => 14],
            ],
            (new Model())
                ->setDiscussionThread((new DiscussionThread())->setId(14)),
        ];

        // Storage file upload
        $storage = (new Storage())
            ->setHash('1234567890123456789012345678901234567890')
            ->setBasename('test.gif');

        yield 'file upload' => [
            [
                'file' => 'file:1234567890123456789012345678901234567890:test.gif',
            ],
            (new Model())
                ->setStorage($storage)
                ->setFilename('test.gif'),
            $storage,
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
            ->setBody('test')
            ->setCreator((new User())->setId($oldId))
            ->setRecipient((new User())->setId($oldId))
            ->setDiscussionThread((new DiscussionThread())->setId($oldId))
            ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
            ->setFilename('test.gif');


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


        // created/updated/deleted at are not mutable
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


        // Body is mutable
        yield 'update body' => [
            ['body' => 'new body',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body'),
        ];

        yield 'null body' => [
            ['body' => null,],
            (clone $baseModel),
            (clone $baseModel)->setBody(null),
        ];

        yield 'update creator' => [
            ['creator' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId($newId)),
        ];

        // Creator cannot be set to null once created:
        // this error case is covered in `testInvokeUpdateInvalidValues`.
//        yield 'null creator' => [
//            ['creator' => null,],
//            (clone $baseModel),
//            (clone $baseModel)
//        ];

        yield 'null creator id' => [
            ['creator' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId(null)),
        ];

        yield 'update discussion thread' => [
            ['discussionThread' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setDiscussionThread((new DiscussionThread())->setId($newId)),
        ];

        yield 'null discussion thread' => [
            ['discussionThread' => null,],
            (clone $baseModel),
            (clone $baseModel)->setDiscussionThread(null),
        ];

        yield 'null discussion thread ID' => [
            ['discussionThread' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setDiscussionThread((new DiscussionThread())->setId(null)),
        ];

        yield 'update recipient' => [
            ['recipient' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setRecipient((new User())->setId($newId)),
        ];

        // Likewise recipient can't be set to null once created:
        // likewise this is covered in `testInvokeUpdateInvalidValues`.
//        yield 'null recipient' => [
//            ['recipient' => null,],
//            (clone $baseModel),
//            (clone $baseModel)
//        ];

        yield 'null recipient ID' => [
            ['recipient' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setRecipient((new User())->setId(null)),
        ];

        // File is mutable, by either a file upload or a storage+filename
        // Will need to mock the collector factory though
        $storage1 = (new Storage())
            ->setHash('9876543210987654321098765432109876543210')
            ->setBasename('test.png');

        yield 'update file' => [
            [
                'file' => 'file:9876543210987654321098765432109876543210:test.png',
            ],
            (clone $baseModel),
            (clone $baseModel)
                ->setStorage($storage1)
                ->setFilename('test.png'),
            $storage1,
        ];

        yield 'null file' => [
            [
                'file' => null,
            ],
            (clone $baseModel),
            (clone $baseModel)
                ->setStorage(null)
                ->setFilename(null),
        ];


        // Storage and filename are mutable:
        // but will they ever be mutated via their individual fields?
        $storage2 = (new Storage())->setHash('12345');
        yield 'update storage' => [
            [
                'storage' => ['hash' => '12345'],
            ],
            (clone $baseModel),
            (clone $baseModel)->setStorage($storage2),
            $storage2,
        ];

        yield 'null storage' => [
            [
                'storage' => null,
            ],
            (clone $baseModel),
            (clone $baseModel)
                ->setStorage(null),
        ];

        yield 'null storage hash' => [
            [
                'storage' => ['hash' => null],
            ],
            (clone $baseModel),
            (clone $baseModel)
                ->setStorage(null),
        ];

        yield 'update filename' => [
            [
                'filename' => 'test.png',
            ],
            (clone $baseModel),
            (clone $baseModel)->setFilename('test.png'),
        ];

        yield 'null filename' => [
            [
                'filename' => null,
            ],
            (clone $baseModel),
            (clone $baseModel)
                ->setFilename(null),
        ];
    }


    /**
     * Data provider for `testInvokeUpdateInvalidValues`.
     *
     * @return iterable
     */
    public static function getTestInvokeUpdateInvalidValuesData(): iterable
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
     *
     * @param array         $input
     * @param Model         $expected
     * @param IStorage|null $storage   If provided: the storage object the
     *                                 converter will return
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeCreateData')]
    public function testInvokeCreate(array $input, Model $expected, ?IStorage $storage = null): void
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
     * @param IStorage|null $storage    If provided: the storage object the
     *                                  converter will return
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeUpdateData')]
    public function testInvokeUpdate(
        array $input,
        Model $existing,
        Model $expected,
        ?IStorage $storage = null,
    ): void {
        $converter = $this->createConverter($storage);

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
        $this->expectException(InvalidArgumentException::class);
        $converter = $this->createConverter();

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
     * @return AllFields
     */
    private function createConverter(?IStorage $storage = null): AllFields
    {
        if ($storage) {
            $collectorMock = $this->createMock(Collector::class);
            $collectorMock->method('getFirst')->willReturn($storage);

            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $collectorFactoryMock->method('build')->willReturn($collectorMock);
            return new AllFields($collectorFactoryMock);
        }

        /** @var CollectorFactory|MockObject $collectorFactoryMock */
        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
        return new AllFields($collectorFactoryMock);
    }
}
