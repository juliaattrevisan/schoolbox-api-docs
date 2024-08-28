<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\MutableFieldsOnly;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from an array to model representation of learning moment
 * evidence, when only the subset of fields which is mutable by API calls is
 * converted.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel\MutableFieldsOnly::class)]
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

        // Should ID be settable by this?
        yield 'id + body' => [
            [
                'id'   => 3,
                'body' => 'something',
            ],
            (new Model())->setBody('something'),
        ];

        yield 'dates' => [
            [
                'createdAt' => '2020-01-01T00:00:00+00:00',
                'updatedAt' => '2020-02-01T00:00:00+00:00',
                'deletedAt' => '2020-03-01T00:00:00+00:00',
            ],
            (new Model()),
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
            (new Model()),
        ];

        yield 'discussion thread' => [
            [
                'discussionThread' => ['id' => 14],
            ],
            (new Model()),
        ];

        // Storage file upload: require a mock here
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
            ->setType(Model::MOMENT_TYPE_UPLOAD)
            ->setDiscussionThread((new DiscussionThread())->setId($oldId))
            ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
            ->setFilename('test.gif');


        // ID is not mutable
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


        // created/updated/deleted at are not mutable
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


        // Creator is not mutable
        yield 'update creator' => [
            ['creator' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        // Creator cannot be set to null once created, but this converter
        // refuses to update that field anyway
        yield 'null creator' => [
            ['creator' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null creator id' => [
            ['creator' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel),
        ];


        // Discussion thread is not mutable
        yield 'update discussion thread' => [
            ['discussionThread' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null discussion thread' => [
            ['discussionThread' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null discussion thread ID' => [
            ['discussionThread' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel),
        ];


        // Recipient is not mutable
        yield 'update recipient' => [
            ['recipient' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel),
        ];

        // Likewise: recipient cannot be set to null once created, but this
        // converter refuses to update that field anyway
        yield 'null recipient' => [
            ['recipient' => null,],
            (clone $baseModel),
            (clone $baseModel),
        ];

        yield 'null recipient ID' => [
            ['recipient' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel),
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
            (clone $baseModel)
                ->setFilename('test.png'),
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
     * Test that creation of a new model from an array works as expected.
     *
     *
     * @param array         $input
     * @param Model         $expected
     * @param IStorage|null $storage    If provided: the storage object the
     *                                  converter will return
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
     * Helper for creating the converter if it is null.
     *
     * If it's null, it creates a converter which contains a mock of the storage
     * object collector factory.
     *
     * @param IStorage|null $storage
     * @return MutableFieldsOnly
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
