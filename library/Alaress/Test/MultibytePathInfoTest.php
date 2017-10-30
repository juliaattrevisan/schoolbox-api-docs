<?php

namespace Alaress\Test;

use PHPUnit\Framework\TestCase;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
        //    input,              path,   basename,            ext,     filename
        return [
            ['foo.doc', null, 'foo.doc', 'doc', 'foo'],
            ['foo.', null, 'foo.', '', 'foo'],
            ['.foo', null, '.foo', 'foo', null],
            ['foo', null, 'foo', null, 'foo'],
            ['foo.tar.gz', null, 'foo.tar.gz', 'gz', 'foo.tar'],
            ['foo.tar..gz', null, 'foo.tar..gz', 'gz', 'foo.tar.'],
            ['怎样节约用水.jpeg', null, '怎样节约用水.jpeg', 'jpeg', '怎样节约用水'],
            ['_怎样节约用水.jpeg', null, '_怎样节约用水.jpeg', 'jpeg', '_怎样节约用水'],
            ['אַהֲוָא.jpeg', null, 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            ['/foo/אַהֲוָא.jpeg', '/foo', 'אַהֲוָא.jpeg', 'jpeg', 'אַהֲוָא'],
            ['/foo/_אַהֲוָא.jpeg', '/foo', '_אַהֲוָא.jpeg', 'jpeg', '_אַהֲוָא'],
            ['/foo/bar/_אַהֲוָא.jpeg', '/foo/bar', '_אַהֲוָא.jpeg', 'jpeg', '_אַהֲוָא'],
            ['/foo/bar/', '/foo', 'bar', null, 'bar'],
        ];
    }


    /**
     * @dataProvider data
     * @param string $input
     * @param string $expectedDirname
     * @param string $expectedBasename
     * @param string $expectedExtension
     * @param string $expectedFilename
     */
    public function testMultiBytePathInfo(
        $input,
        $expectedDirname,
        $expectedBasename,
        $expectedExtension,
        $expectedFilename
    ) {
        $info = mb_pathinfo($input);

        $expected = [
            'dirname'   => $expectedDirname,
            'basename'  => $expectedBasename,
            'extension' => $expectedExtension,
            'filename'  => $expectedFilename,
        ];

        // as with pathinfo(), if any part is empty, it is not included in the returned array
        foreach ($expected as $field => $value) {
            if (is_null($value)) {
                $this->assertArrayNotHasKey($field, $info, "Key '$field' is present when it should not be");
            } else {
                $this->assertArrayHasKey($field, $info, "Key '$field' is not present when it should be");
                $this->assertEquals($value, $info[$field], "Value for '$field' does not match");
            }
        }
    }
}
