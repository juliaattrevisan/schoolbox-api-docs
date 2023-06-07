<?php

namespace Alaress\Test;

use PHPUnit\Framework\TestCase;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class MultibytePathInfoTest extends TestCase
{
    /**
     * Data provider for mb_pathinfo test
     *
     * @return array
     */
    public function data()
    {
        // [input, path, basename, ext, filename]
        return [
            'foo.doc'                           => ['foo.doc', null, 'foo.doc', 'doc', 'foo'],
            'foo.'                              => ['foo.', null, 'foo.', '', 'foo'],
            '.foo'                              => ['.foo', null, '.foo', 'foo', null],
            'foo'                               => ['foo', null, 'foo', null, 'foo'],
            'foo.tar.gz'                        => ['foo.tar.gz', null, 'foo.tar.gz', 'gz', 'foo.tar'],
            'foo.tar..gz'                       => ['foo.tar..gz', null, 'foo.tar..gz', 'gz', 'foo.tar.'],
            '怎样节约用水.jpeg'                 => [
                '怎样节约用水.jpeg',
                null,
                '怎样节约用水.jpeg',
                'jpeg',
                '怎样节约用水',
            ],
            '_怎样节约用水.jpeg'                => [
                '_怎样节约用水.jpeg',
                null,
                '_怎样节约用水.jpeg',
                'jpeg',
                '_怎样节约用水',
            ],
            'אַהֲוָא.jpeg'                      => ['אַהֲוָא.jpeg', null, 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            '/foo/אַהֲוָא.jpeg'                 => ['/foo/אַהֲוָא.jpeg', '/foo', 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            '/foo/_אַהֲוָא.jpeg'                => ['/foo/_אַהֲוָא.jpeg', '/foo', '_אַהֲוָא.jpeg', 'jpeg', '_אַהֲוָא'],
            '/foo/bar/_אַהֲוָא.jpeg'            => [
                '/foo/bar/_אַהֲוָא.jpeg',
                '/foo/bar',
                '_אַהֲוָא.jpeg',
                'jpeg',
                '_אַהֲוָא',
            ],
            '/foo/bar/'                         => ['/foo/bar/', '/foo', 'bar', null, 'bar'],
            'clone_adobeppcc.jpg?itok=AlstqiJ0' => [
                'clone_adobeppcc.jpg?itok=AlstqiJ0',
                null,
                'clone_adobeppcc.jpg?itok=AlstqiJ0',
                'jpg',
                'clone_adobeppcc',
            ],
            '/files/350564/original/...'        => [
                '/files/350564/original/file-20200731-29-1pb85px.jpg?ixlib=rb-1.1.0&rect=0%2C184%2C2995%2C1495&q=45&auto=format&w=1356&h=668&fit=crop',
                '/files/350564/original',
                'file-20200731-29-1pb85px.jpg?ixlib=rb-1.1.0&rect=0%2C184%2C2995%2C1495&q=45&auto=format&w=1356&h=668&fit=crop',
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
     * @dataProvider data
     * @param string      $input
     * @param string|null $expectedDirname
     */
    public function testMultiByteDirname(
        string $input,
        ?string $expectedDirname,
    ) {
        $info = mb_pathinfo($input);
        if (!isset($expectedDirname)) {
            $this->assertArrayNotHasKey('dirname', $info);
        } else {
            $this->assertSame($expectedDirname, $info['dirname']);
        }
    }

    /**
     * @dataProvider data
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     */
    public function testMultiByteBasename(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
    ) {
        $info = mb_pathinfo($input);
        if (!isset($expectedBasename)) {
            $this->assertArrayNotHasKey('basename', $info);
        } else {
            $this->assertSame($expectedBasename, $info['basename']);
        }
    }

    /**
     * @dataProvider data
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     * @param string|null $expectedExtension
     */
    public function testMultiByteExtension(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
        ?string $expectedExtension,
    ) {
        $info = mb_pathinfo($input);
        if (!isset($expectedExtension)) {
            $this->assertArrayNotHasKey('extension', $info);
        } else {
            $this->assertSame($expectedExtension, $info['extension']);
        }
    }

    /**
     * @dataProvider data
     * @param string      $input
     * @param string|null $expectedDirname
     * @param string|null $expectedBasename
     * @param string|null $expectedExtension
     * @param string|null $expectedFilename
     */
    public function testMultiByteFilename(
        string $input,
        ?string $expectedDirname,
        ?string $expectedBasename,
        ?string $expectedExtension,
        ?string $expectedFilename,
    ) {
        $info = mb_pathinfo($input);
        if (!isset($expectedFilename)) {
            $this->assertArrayNotHasKey('filename', $info);
        } else {
            $this->assertSame($expectedFilename, $info['filename']);
        }
    }
}
