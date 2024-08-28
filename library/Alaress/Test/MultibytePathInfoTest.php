<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Test;

use PHPUnit\Framework\TestCase;

/**
 * Test for parsing multibyte path info.
 *
 * `mb_pathinfo` no longer exists, as `pathinfo(parse_url($path, PHP_URL_PATH))`
 * is multi-byte aware AFAICT: this documents what `mb_pathinfo` used to do.
 * The key differences were:
 * * `mb_pathinfo` attempted to include the query string in the basename:
 *   `parse_url` strips them before they even get to `pathinfo`
 * * `mb_pathinfo` returned null dirname if the path had no dir:
 *   `pathinfo` returns '.'
 * * `mb_pathinfo` returned null filename or basename if the path didn't have
 *    those attributes: `pathinfo` returns an empty string
 */
class MultibytePathInfoTest extends TestCase
{
    /**
     * Data provider for mb_pathinfo test
     *
     * @return array
     */
    public static function data()
    {
        // [input, path, basename, ext, filename]
        return [
            'foo.doc'                           => ['foo.doc', '.', 'foo.doc', 'doc', 'foo'],
            'foo.'                              => ['foo.', '.', 'foo.', '', 'foo'],
            '.foo'                              => ['.foo', '.', '.foo', 'foo', ''],
            'foo'                               => ['foo', '.', 'foo', '', 'foo'],
            'foo.tar.gz'                        => ['foo.tar.gz', '.', 'foo.tar.gz', 'gz', 'foo.tar'],
            'foo.tar..gz'                       => ['foo.tar..gz', '.', 'foo.tar..gz', 'gz', 'foo.tar.'],
            '怎样节约用水.jpeg'                 => [
                '怎样节约用水.jpeg',
                '.',
                '怎样节约用水.jpeg',
                'jpeg',
                '怎样节约用水',
            ],
            '_怎样节约用水.jpeg'                => [
                '_怎样节约用水.jpeg',
                '.',
                '_怎样节约用水.jpeg',
                'jpeg',
                '_怎样节约用水',
            ],
            'אַהֲוָא.jpeg'                      => ['אַהֲוָא.jpeg', '.', 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            '/foo/אַהֲוָא.jpeg'                 => ['/foo/אַהֲוָא.jpeg', '/foo', 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            '/foo/_אַהֲוָא.jpeg'                => ['/foo/_אַהֲוָא.jpeg', '/foo', '_אַהֲוָא.jpeg', 'jpeg', '_אַהֲוָא'],
            '/foo/bar/_אַהֲוָא.jpeg'            => [
                '/foo/bar/_אַהֲוָא.jpeg',
                '/foo/bar',
                '_אַהֲוָא.jpeg',
                'jpeg',
                '_אַהֲוָא',
            ],
            '/foo/bar/'                         => ['/foo/bar/', '/foo', 'bar', '', 'bar'],
            'clone_adobeppcc.jpg?itok=AlstqiJ0' => [
                'clone_adobeppcc.jpg?itok=AlstqiJ0',
                '.',
                'clone_adobeppcc.jpg',
                'jpg',
                'clone_adobeppcc',
            ],
            '/files/350564/original/...'        => [
                '/files/350564/original/file-20200731-29-1pb85px.jpg?ixlib=rb-1.1.0&rect=0%2C184%2C2995%2C1495&q=45&auto=format&w=1356&h=668&fit=crop',
                '/files/350564/original',
                'file-20200731-29-1pb85px.jpg',
                'jpg',
                'file-20200731-29-1pb85px',
            ],
            '/assets/img/favicon.6c717b9f.svg'  => [
                '/assets/img/favicon.6c717b9f.svg',
                '/assets/img',
                'favicon.6c717b9f.svg',
                'svg',
                'favicon.6c717b9f',
            ],
        ];
    }


    /**
     * @param string      $input
     * @param string|null $expectedDirname
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('data')]
    public function testMultiByteDirname(
        string $input,
        ?string $expectedDirname,
    ) {
        $info = pathinfo(parse_url($input, PHP_URL_PATH), PATHINFO_DIRNAME);
        $this->assertSame($expectedDirname, $info);
    }

    /**
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('data')]
    public function testMultiByteBasename(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
    ) {
        $info = pathinfo(parse_url($input, PHP_URL_PATH), PATHINFO_BASENAME);
        $this->assertSame($expectedBasename, $info);
    }

    /**
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     * @param string|null $expectedExtension
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('data')]
    public function testMultiByteExtension(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
        ?string $expectedExtension,
    ) {
        $info = pathinfo(parse_url($input, PHP_URL_PATH), PATHINFO_EXTENSION);
        $this->assertSame($expectedExtension, $info);
    }

    /**
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     * @param string|null $expectedExtension
     * @param string|null $expectedFilename
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('data')]
    public function testMultiByteFilename(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
        ?string $expectedExtension,
        ?string $expectedFilename,
    ) {
        $info = pathinfo(parse_url($input, PHP_URL_PATH), PATHINFO_FILENAME);
        $this->assertSame($expectedFilename, $info);
    }
}
