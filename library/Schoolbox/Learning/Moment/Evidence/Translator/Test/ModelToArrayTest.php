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
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from a model to array representation of learning moment
 * evidence.
 *
 * @covers \Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray
 */
class ModelToArrayTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        yield 'empty' => [new Model(), []];

        yield 'id + body' => [
            (new Model())->setId(3)->setBody('something'),
            [
                'id'   => 3,
                'body' => 'something',
            ],
        ];

        $timezone = new DateTimeZone('+00:00');
        yield 'dates - deleted at not included' => [
            (new Model())
                ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone)),
            [
                'createdAt' => '2020-01-01T00:00:00+00:00',
                'updatedAt' => '2020-02-01T00:00:00+00:00',
                'deletedAt' => '2020-03-01T00:00:00+00:00',
            ],
        ];

        yield 'storage' => [
            (new Model())
                ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
                ->setFilename('test.gif'),
            [
                'storage'  => [
                    'hash' => '1234567890123456789012345678901234567890',
                ],
                'filename' => 'test.gif',
            ],
        ];

        yield 'users' => [
            (new Model())
                ->setCreator((new User())->setId(55))
                ->setRecipient((new User())->setId(193)),
            [
                'creator'   => ['id' => 55],
                'recipient' => ['id' => 193],
            ],
        ];

        yield 'discussion thread' => [
            (new Model())
                ->setDiscussionThread((new DiscussionThread())->setId(14)),
            [
                'discussionThread' => ['id' => 14],
            ],
        ];
    }


    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray::__invoke
     *
     * @param Model $input
     * @param array $expected
     */
    public function testInvoke(Model $input, array $expected): void
    {
        self::assertEquals($expected, (new ModelToArray())($input));
    }
}
