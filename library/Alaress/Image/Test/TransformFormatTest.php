<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Test;

use Alaress\Image\TransformFormat;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Alaress\Image\TransformFormat
 */
class TransformFormatTest extends TestCase
{
    /**
     * @dataProvider getTestConstructData
     * @covers \Alaress\Image\TransformFormat::__construct
     *
     * @param string $mime
     * @param string $expectedFormat
     * @param string $expectedMime
     * @return void
     */
    public function testConstruct(string $mime, string $expectedFormat, string $expectedMime): void
    {
        $transformFormat = new TransformFormat($mime);
        $this->assertSame($expectedFormat, $transformFormat->getFormat());
        $this->assertSame($expectedMime, $transformFormat->getMime());
    }

    /**
     * Data provider for `testConstruct`
     *
     * @return iterable
     */
    public function getTestConstructData(): iterable
    {
        // Supported mimetypes
        yield 'image/heif (transforms to jpg)' => [
            'image/heif',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/heic (transforms to jpg)' => [
            'image/heic',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/tiff (transforms to jpg)' => [
            'image/tiff',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/jpeg' => [
            'image/jpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/bmp (transforms to jpg)' => [
            'image/bmp',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/pjpeg (transforms to jpg)' => [
            'image/pjpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/gif' => [
            'image/gif',
            'gif',
            'image/gif',
        ];
        yield 'image/png' => [
            'image/png',
            'png',
            'image/png',
        ];
        yield 'image/webp' => [
            'image/webp',
            'webp',
            'image/webp',
        ];
        yield 'image/svg+xml' => [
            'image/svg+xml',
            'svg',
            'image/svg+xml',
        ];

        // Supported mimetypes with proper unregistered tree (x.) prefix
        yield 'image/x.heif (transforms to jpg)' => [
            'image/x.heif',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.heic (transforms to jpg)' => [
            'image/x.heic',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.tiff (transforms to jpg)' => [
            'image/x.tiff',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.jpeg' => [
            'image/x.jpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.bmp (transforms to jpg)' => [
            'image/x.bmp',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.pjpeg (transforms to jpg)' => [
            'image/x.pjpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.gif' => [
            'image/x.gif',
            'gif',
            'image/gif',
        ];
        yield 'image/x.png' => [
            'image/x.png',
            'png',
            'image/png',
        ];
        yield 'image/x.webp' => [
            'image/x.webp',
            'webp',
            'image/webp',
        ];
        yield 'image/x.svg+xml' => [
            'image/x.svg+xml',
            'svg',
            'image/svg+xml',
        ];

        // Supported mimetypes with improper unregistered tree (x-) prefix
        yield 'image/x-heif (transforms to jpg)' => [
            'image/x-heif',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-heic (transforms to jpg)' => [
            'image/x-heic',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-tiff (transforms to jpg)' => [
            'image/x-tiff',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-jpeg' => [
            'image/x-jpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-bmp (transforms to jpg)' => [
            'image/x-bmp',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-pjpeg (transforms to jpg)' => [
            'image/x-pjpeg',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-gif' => [
            'image/x-gif',
            'gif',
            'image/gif',
        ];
        yield 'image/x-png' => [
            'image/x-png',
            'png',
            'image/png',
        ];
        yield 'image/x-webp' => [
            'image/x-webp',
            'webp',
            'image/webp',
        ];
        yield 'image/x-svg+xml' => [
            'image/x-svg+xml',
            'svg',
            'image/svg+xml',
        ];
    }
}
