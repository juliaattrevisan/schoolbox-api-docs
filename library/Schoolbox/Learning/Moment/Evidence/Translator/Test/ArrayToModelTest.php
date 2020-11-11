<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel;
use Schoolbox\Resource\Attachment\Collector\Collector;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from an array to model representation of learning moment
 * evidence.
 *
 * @covers \Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel
 */
class ArrayToModelTest extends TestCase
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
        yield 'dates - deleted at not included' => [
            [
                'createdAt' => '2020-01-01T00:00:00+00:00',
                'updatedAt' => '2020-02-01T00:00:00+00:00',
                'deletedAt' => '2020-03-01T00:00:00+00:00',
            ],
            (new Model())
                ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone)),
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

        // Storage file upload: require a mock here
        $storage = (new Storage())
            ->setHash('1234567890123456789012345678901234567890')
            ->setBasename('test.gif');
        $collectorMock = $this->createMock(Collector::class);
        $collectorMock->method('getFirst')->willReturn($storage);

        /** @var CollectorFactory|MockObject $collectorFactoryMock */
        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
        $collectorFactoryMock->method('build')->willReturn($collectorMock);

        yield 'file upload' => [
            [
                'file' => 'file:1234567890123456789012345678901234567890:test.gif',
            ],
            (new Model())
                ->setStorage($storage)
                ->setFilename('test.gif'),
            new ArrayToModel($collectorFactoryMock),
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
            ->setBody('test')
            ->setCreator((new User())->setId($oldId))
            ->setRecipient((new User())->setId($oldId))
            ->setDiscussionThread((new DiscussionThread())->setId($oldId))
            ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
            ->setFilename('test.gif');

        // TODO determine if ID should even be settable by this
        // It feels wrong, because IDs should be immutable.
        // However, should that be the responsibility of this class?
        yield 'update id' => [
            ['id' => $newId,],
            (clone $baseModel),
            (clone $baseModel)->setId($newId)
        ];

        yield 'null id' => [
            ['id' => null,],
            (clone $baseModel),
            (clone $baseModel)->setId(null)
        ];


        // Likewise, should created/updated at be mutable?
        yield 'update created at' => [
            ['createdAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(new Alaress_Date_Format($newDate, DATE_ATOM, $timezone))
        ];

        yield 'null created at' => [
            ['createdAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(null)
        ];

        yield 'update updated at' => [
            ['updatedAt' => $newDate,],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedAt(new Alaress_Date_Format($newDate, DATE_ATOM, $timezone))
        ];

        yield 'null updated at' => [
            ['updatedAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setUpdatedAt(null)
        ];


        // Body should be mutable
        yield 'update body' => [
            ['body' => 'new body',],
            (clone $baseModel),
            (clone $baseModel)->setBody('new body')
        ];

        yield 'null body' => [
            ['body' => null,],
            (clone $baseModel),
            (clone $baseModel)->setBody(null)
        ];


        // Creator should not be mutable
        yield 'update creator' => [
            ['creator' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId($newId))
        ];

//        // Creator is not nullable: this should be rejected
//        yield 'null creator' => [
//            ['creator' => null,],
//            (clone $baseModel),
//            (clone $baseModel)->setCreator(null)
//        ];

        // What happens if creator's ID is null?
        yield 'null creator ID' => [
            ['creator' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId(null))
        ];


        // Discussion thread should not be mutable
        yield 'update discussion thread' => [
            ['discussionThread' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setDiscussionThread((new DiscussionThread())->setId($newId))
        ];

        yield 'null discussion thread ID' => [
            ['discussionThread' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setDiscussionThread((new DiscussionThread())->setId(null))
        ];


        // Recipient should not be mutable
        yield 'update recipient' => [
            ['creator' => ['id' => $newId,],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId($newId))
        ];

        yield 'null recipient ID' => [
            ['creator' => ['id' => null],],
            (clone $baseModel),
            (clone $baseModel)->setCreator((new User())->setId(null))
        ];




//        $timezone = new DateTimeZone('+00:00');
//        yield 'dates - deleted at not included' => [
//            [
//                'createdAt' => '2020-01-01T00:00:00+00:00',
//                'updatedAt' => '2020-02-01T00:00:00+00:00',
//                'deletedAt' => '2020-03-01T00:00:00+00:00',
//            ],
//            (new Model())
//                ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
//                ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone)),
//        ];
//
//        yield 'storage' => [
//            [
//                'storage'  => [
//                    'hash' => '1234567890123456789012345678901234567890',
//                ],
//                'filename' => 'test.gif',
//            ],
//            (new Model())
//                ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
//                ->setFilename('test.gif'),
//        ];
//
//        yield 'users' => [
//            [
//                'creator'   => ['id' => 55],
//                'recipient' => ['id' => 193],
//            ],
//            (new Model())
//                ->setCreator((new User())->setId(55))
//                ->setRecipient((new User())->setId(193)),
//        ];
//
//        yield 'discussion thread' => [
//            [
//                'discussionThread' => ['id' => 14],
//            ],
//            (new Model())
//                ->setDiscussionThread((new DiscussionThread())->setId(14)),
//        ];
//
//        // Storage file upload: require a mock here
//        $storage = (new Storage())
//            ->setHash('1234567890123456789012345678901234567890')
//            ->setBasename('test.gif');
//        $collectorMock = $this->createMock(Collector::class);
//        $collectorMock->method('getFirst')->willReturn($storage);
//
//        /** @var CollectorFactory|MockObject $collectorFactoryMock */
//        $collectorFactoryMock = $this->createMock(CollectorFactory::class);
//        $collectorFactoryMock->method('build')->willReturn($collectorMock);
//
//        yield 'file upload' => [
//            [
//                'file' => 'file:1234567890123456789012345678901234567890:test.gif',
//            ],
//            (new Model())
//                ->setStorage($storage)
//                ->setFilename('test.gif'),
//            new ArrayToModel($collectorFactoryMock),
//        ];
    }


    /**
     * @dataProvider getTestInvokeCreateData
     * @covers       \Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel::__invoke
     *
     * @param array             $input
     * @param Model             $expected
     * @param ArrayToModel|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    public function testInvokeCreate(array $input, Model $expected, ArrayToModel $converter = null): void
    {
        if (!$converter) {
            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $converter ??= new ArrayToModel($collectorFactoryMock);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input));
    }


    /**
     * @dataProvider getTestInvokeUpdateData
     * @covers       \Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel::__invoke
     *
     * @param array             $input
     * @param Model             $existing
     * @param Model             $expected
     * @param ArrayToModel|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    public function testInvokeUpdate(
        array $input,
        Model $existing,
        Model $expected,
        ArrayToModel $converter = null
    ): void {
        if (!$converter) {
            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $converter ??= new ArrayToModel($collectorFactoryMock);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input, $existing));
    }
}
