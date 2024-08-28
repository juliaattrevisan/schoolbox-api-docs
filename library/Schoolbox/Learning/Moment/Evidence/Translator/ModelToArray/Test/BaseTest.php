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
use Schoolbox\Curriculum\Node\Model as CurriculumNode;
use Schoolbox\Discussion\Thread\Model as DiscussionThread;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Moment\Evidence\Model;
use Schoolbox\Learning\Moment\Evidence\Tag\Model as EvidenceTag;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Base;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Tests for converting from a model to array representation of learning moment
 * evidence.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Base::class)]
class BaseTest extends TestCase
{
    /**
     * Data provider for `testInvoke`.
     *
     * @return iterable
     */
    public static function getTestInvokeData(): iterable
    {
        yield 'empty fields' => [
            new Model(),
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
                'curriculumNodes'  => null,
                'tags'             => null,
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
                ->setType(Model::MOMENT_TYPE_UPLOAD)
                ->setDiscussionThread((new DiscussionThread())->setId(14))
                ->setCurriculumNodes(
                    (new GenericCollection(new CurriculumNode()))->add((new CurriculumNode())->setId(500)->setName('Test Curriculum')->setCode(trim('TEST500 ')))
                )
                ->setTags((new GenericCollection(new EvidenceTag()))->add((new EvidenceTag())->setId(1)->setName('Class Work'))),
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
                'curriculumNodes'  => new Collection([['id' => 500, 'name' => 'Test Curriculum', 'code' => 'TEST500']]),
                'tags'             => new Collection([['id' => 1, 'name' => 'Class Work']]),
            ],
        ];
    }


    /**
     * @param Model $input
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
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
