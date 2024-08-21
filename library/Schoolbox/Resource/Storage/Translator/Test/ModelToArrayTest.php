<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Translator\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\Translator\ModelToArray;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Storage\Translator\ModelToArray::class)]
class ModelToArrayTest extends TestCase
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
                'name'   => '.',
                'hash'   => null,
                'mime'   => '',
                'type'   => null,
                'size'   => null,
                '_links' => [],
            ],
        ];

        yield 'document' => [
            (new Model())
                ->setBasename('document.docx')
                ->setMimetype(
                    (new Mimetype())
                        ->setMime('document/ms-word')
                        ->setIconFile('ms-word.svg')
                )
                ->setHash('1234567890')
                ->setSize(12345),
            [
                'name'   => 'document.docx',
                'hash'   => '1234567890',
                'mime'   => 'document/ms-word',
                'type'   => 'document',
                'size'   => 12345,
                '_links' => [
                    'fetch' => '/storage/fetch.php?hash=1234567890',
                    'icon'  => '/images/mime/ms-word.svg',
                ],
            ]
        ];

        yield 'image' => [
            (new Model())
                ->setBasename('image.png')
                ->setMimetype(
                    (new Mimetype())
                        ->setMime('image/png')
                        ->setIconFile('image-png.svg')
                )
                ->setHash('999000')
                ->setSize(432166),
            [
                'name'   => 'image.png',
                'hash'   => '999000',
                'mime'   => 'image/png',
                'type'   => 'image',
                'size'   => 432166,
                '_links' => [
                    'fetch' => '/storage/fetch.php?hash=999000',
                    'icon'  => '/images/mime/image-png.svg',
                    'image' => '/storage/image.php?hash=999000',
                ],
            ]
        ];

        yield 'audio' => [
            (new Model())
                ->setBasename('audio.mp4')
                ->setMimetype(
                    (new Mimetype())
                        ->setMime('audio/mp4')
                        ->setIconFile('audio-mp4.svg')
                )
                ->setHash('54321')
                ->setSize(1_000_000),
            [
                'name'   => 'audio.mp4',
                'hash'   => '54321',
                'mime'   => 'audio/mp4',
                'type'   => 'audio',
                'size'   => 1_000_000,
                '_links' => [
                    'fetch'  => '/storage/fetch.php?hash=54321',
                    'icon'   => '/images/mime/audio-mp4.svg',
                    'stream' => '/cms/sendVideo.php?id=54321&type=storage',
                ],
            ]
        ];

        yield 'video' => [
            (new Model())
                ->setBasename('video.mp4')
                ->setMimetype(
                    (new Mimetype())
                        ->setMime('video/mp4')
                        ->setIconFile('video-mp4.svg')
                )
                ->setHash('333')
                ->setSize(2_000_000),
            [
                'name'   => 'video.mp4',
                'hash'   => '333',
                'mime'   => 'video/mp4',
                'type'   => 'video',
                'size'   => 2_000_000,
                '_links' => [
                    'fetch'  => '/storage/fetch.php?hash=333',
                    'icon'   => '/images/mime/video-mp4.svg',
                    'stream' => '/cms/sendVideo.php?id=333&type=storage',
                    'poster' => '/cms/videoThumb.php?size=poster&id=333&type=storage',
                ],
            ]
        ];
    }


    /**
     * @param Model $input
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvokeData')]
    public function testInvoke(Model $input, array $expected): void
    {
        $mockPrefixGenerator = $this->createMock(PrefixGenerator::class);
        $mockPrefixGenerator->method('generate')->willReturn('');
        self::assertSame($expected, (new ModelToArray($mockPrefixGenerator))($input));
    }
}
