<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\Test;

use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit\Framework\TestCase;
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
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        yield 'empty' => [[], new Model()];

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
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\Learning\Moment\Evidence\Translator\ArrayToModel::__invoke
     *
     * @param array             $input
     * @param Model             $expected
     * @param ArrayToModel|null $converter
     *
     * @noinspection PhpDocMissingThrowsInspection This ought not be thrown by the converter
     */
    public function testInvoke(array $input, Model $expected, ArrayToModel $converter = null): void
    {
        if (!$converter) {
            /** @var CollectorFactory|MockObject $collectorFactoryMock */
            $collectorFactoryMock = $this->createMock(CollectorFactory::class);
            $converter ??= new ArrayToModel($collectorFactoryMock);
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        self::assertEquals($expected, $converter($input));
    }
}
