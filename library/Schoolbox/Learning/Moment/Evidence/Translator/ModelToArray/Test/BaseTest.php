<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Test;

use Alaress\HTMLPurifier\Adapter\MockAdapter;
use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Base;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from a model to array representation of learning moment
 * evidence.
 *
 * @covers \Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Base
 */
class BaseTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public function getTestInvokeData(): iterable
    {
        yield 'empty fields' => [
            (new Model())
                ->setCurriculumNodes(new GenericCollection(new \Schoolbox\Curriculum\Node\Model()))
                ->setTags(new GenericCollection(new \Schoolbox\Learning\Moment\Evidence\Tag\Model())),
            [
                'id'               => null,
                'body'             => null,
                'createdAt'        => null,
                'updatedAt'        => null,
                'storage'          => null,
                'filename'         => null,
                'creator'          => null,
                'recipient'        => null,
                'discussionThread' => null,
                '_links'           => [],
                'curriculumNodes'  => new Collection(),
                'tags'             => new Collection(),
            ],
        ];

        $timezone = new DateTimeZone('+00:00');
        yield 'populated fields' => [
            (new Model())
                ->setId(3)
                ->setBody('something')
                ->setCreatedAt(new Alaress_Date_Format('2020-01-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setUpdatedAt(new Alaress_Date_Format('2020-02-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setDeletedAt(new Alaress_Date_Format('2020-03-01T00:00:00+00:00', DATE_ATOM, $timezone))
                ->setStorage((new Storage())->setHash('1234567890123456789012345678901234567890'))
                ->setFilename('test.gif')
                ->setCreator((new User())->setId(55))
                ->setRecipient((new User())->setId(193))
                ->setDiscussionThread((new DiscussionThread())->setId(14))
                ->setCurriculumNodes(new GenericCollection(new \Schoolbox\Curriculum\Node\Model()))
                ->setTags(new GenericCollection(new \Schoolbox\Learning\Moment\Evidence\Tag\Model())),
            [
                'id'               => 3,
                'body'             => 'something',
                'createdAt'        => '2020-01-01T00:00:00+00:00',
                'updatedAt'        => '2020-02-01T00:00:00+00:00',
                'storage'          => ['hash' => '1234567890123456789012345678901234567890',],
                'filename'         => 'test.gif',
                'creator'          => ['id' => 55],
                'recipient'        => ['id' => 193],
                'discussionThread' => ['id' => 14],
                '_links'           => [],
                'curriculumNodes'  => new Collection(),
                'tags'             => new Collection(),
            ],
        ];
    }


    /**
     * @dataProvider getTestInvokeData
     * @covers       \Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Base::__invoke
     *
     * @param Model $input
     * @param array $expected
     */
    public function testInvoke(Model $input, array $expected): void
    {
        // Test with a stripped-down user and storage translator
        $userTranslator    = static function (?User $user) {
            return $user
                ? ['id' => $user->getId(),]
                : null;
        };
        $storageTranslator = static function (?Storage $storage) {
            return $storage
                ? ['hash' => $storage->getHash(),]
                : null;
        };
        self::assertEquals(
            $expected,
            (new Base($userTranslator, $storageTranslator, new MockAdapter()))($input)
        );
    }
}
