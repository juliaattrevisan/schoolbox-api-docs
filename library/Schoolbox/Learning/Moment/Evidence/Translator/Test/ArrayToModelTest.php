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
        $timezone  = new DateTimeZone('+00:00');
        $baseModel = (new Model())
            ->setId(1)
            ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
            ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone))
            ->setDeletedAt(new Alaress_Date_Format('2020-03-01T00:00:00+00:00', DATE_ATOM, $timezone))
            ->setBody('test')
            ->setCreator((new User())->setId(55))
            ->setRecipient((new User())->setId(193))
            ->setDiscussionThread((new DiscussionThread())->setId(14))
            ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
            ->setFilename('test.gif');

        // TODO determine if ID should even be settable by this
        // It feels wrong, because IDs should be immutable.
        // However, should that be the responsibility of this class?
        yield 'update id' => [
            ['id' => 3,],
            (clone $baseModel),
            (clone $baseModel)->setId(3)
        ];

        yield 'null id' => [
            ['id' => null,],
            (clone $baseModel),
            (clone $baseModel)->setId(null)
        ];

        // Likewise, should created at be mutable?
        yield 'update created at' => [
            ['createdAt' => '2020-02-01T00:00:00+00:00',],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone))
        ];

        yield 'null created at' => [
            ['createdAt' => null,],
            (clone $baseModel),
            (clone $baseModel)->setCreatedAt(null)
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
