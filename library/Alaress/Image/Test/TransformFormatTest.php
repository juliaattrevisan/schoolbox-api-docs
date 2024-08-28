<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Test;

use Alaress\Image\TransformFormat;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Image\TransformFormat::class)]
class TransformFormatTest extends TestCase
{
    /**
     *
     * @param string $mime
     * @param string $expectedFormat
     * @param string $expectedMime
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestConstructData')]
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
    public static function getTestConstructData(): iterable
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
        yield 'image/bmp2 (transforms to jpg)' => [
            'image/bmp2',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/bmp3 (transforms to jpg)' => [
            'image/bmp3',
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

        // Case-insensitive test
        yield 'iMagE/jPeg' => [
            'iMagE/jPeg',
            'jpg',
            'image/jpeg',
        ];

        // Trim test
        yield 'image/jpeg with leading/trailing space' => [
            ' image/jpeg ',
            'jpg',
            'image/jpeg',
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
        yield 'image/x.bmp2 (transforms to jpg)' => [
            'image/x.bmp2',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x.bmp3 (transforms to jpg)' => [
            'image/x.bmp3',
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
        yield 'image/x-bmp2 (transforms to jpg)' => [
            'image/x-bmp2',
            'jpg',
            'image/jpeg',
        ];
        yield 'image/x-bmp3 (transforms to jpg)' => [
            'image/x-bmp3',
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

    /**
     * @param string $mime
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestConstructInvalidMimetypeData')]
    public function testConstructInvalidMimetype(string $mime): void
    {
        $this->expectException(UnexpectedValueException::class);
        new TransformFormat($mime);
    }

    /**
     * Data provider for `testConstructInvalidMimetype`
     *
     * @return iterable
     */
    public static function getTestConstructInvalidMimetypeData(): iterable
    {
        // PSD files are unsupported
        yield 'application/x-photoshop' => ['application/x-photoshop'];
        yield 'application/photoshop' => ['application/photoshop'];
        yield 'application/psd' => ['application/psd'];
        yield 'image/psd' => ['image/psd'];
        yield 'image/vnd.adobe.photoshop' => ['image/vnd.adobe.photoshop'];

        // Unsupported other types
        yield 'text/plain' => ['text/plain'];
        yield 'application/octet-stream' => ['application/octet-stream'];

        // Totally invalid mimetypes
        yield 'invalid' => ['invalid'];
    }
}
