<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Test;

use Alaress\Routing\Trie;
use PHPUnit\Framework\TestCase;

class TrieTest extends TestCase
{
    /**
     * Abstract away sorting
     *
     * @param string $key
     * @return Trie
     */
    private function newTrie($key)
    {
        return new Trie($key, function () {
            return 0;
        });
    }

    /**
     * Test that attempting to retrieve a subordinate key from an empty Trie throws an exception
     *
     *
     */
    public function testGetEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $trie = $this->newTrie('nothingSpecial');
        $trie->get('nonexistentSubordinateKey');
    }


    /**
     * Test that a Trie's key can be retrieved
     */
    public function testGetKey()
    {
        $expected = 'something_somethingKeysomething';

        $trie = $this->newTrie($expected);

        $this->assertEquals($expected, $trie->key());
    }


    /**
     * Test that the return value of a put is a different Trie to the one you put it in
     */
    public function testPut()
    {
        $trie = $this->newTrie('nothingSpecial');
        $subTrie = $trie->put('subordinateKey');

        $this->assertInstanceOf(Trie::class, $subTrie);
        $this->assertNotSame($trie, $subTrie);
    }


    /**
     * Test that a Trie can confirm it does not contain a given subordinate key
     */
    public function testInvalidHas()
    {
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('subordinateKey');

        $this->assertFalse($trie->has('incorrectKey'));
    }


    /**
     * Test that a Trie can confirm it contains a given literal subordinate key
     */
    public function testValidLiteralHas()
    {
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('subordinateKey');

        $this->assertTrue($trie->has('subordinateKey'));
    }


    /**
     * Test that a Trie does NOT match a valid pattern when using `has`
     */
    public function testValidRegexHas()
    {
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('foo/\d+/bar');

        $this->assertFalse($trie->has('foo/12/bar'));
    }


    /**
     * Test that attempting to retrieve a non-existent subordinate key throws an exception
     *
     *
     */
    public function testInvalidGet()
    {
        $this->expectException(\InvalidArgumentException::class);
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('subordinateKey');

        $trie->get('incorrectKey');
    }


    /**
     * Test that a Trie can return a subordinate Trie by literal match
     */
    public function testValidLiteralGet()
    {
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('subordinateKey');

        $this->assertInstanceOf(Trie::class, $trie->get('subordinateKey'));
    }


    /**
     * Test that a Trie can return a subordinate Trie by pattern match
     */
    public function testValidRegexGet()
    {
        $trie = $this->newTrie('nothingSpecial');
        $trie->put('foo/\d+/bar');

        $this->assertInstanceOf(Trie::class, $trie->get('foo/12/bar'));
    }



    /**
     * Test that a payload hierarchy can be retrieved fully
     */
    public function testFullFetch()
    {
        $keysToNest = [2, 4, 6];

        $expectedKey   = 'keykey';
        $expectedValue = 'valval';

        $trie = $this->newTrie('nothingSpecial');
        $trie->set($keysToNest, $expectedKey, $expectedValue);

        $this->assertEquals([ $expectedKey => $expectedValue ], $trie->fetch($keysToNest));
    }


    /**
     * Test that a payload hierarchy can be partially retrieved
     */
    public function testPartialFetch()
    {
        $root = 2;
        $setA = [$root, 4, 6];
        $setB = [$root, 5, 7];

        $expectedKey   = 'keykey';
        $expectedValue = 'valval';

        $trie = $this->newTrie('nothingSpecial');

        $trie->set($setA, $expectedKey, $expectedValue);
        $trie->set($setB, $expectedKey, $expectedValue);

        $actual = $trie->fetch([ $root ]);

        $expected = [
            $setA[1] => [
                $setA[2] => [
                    $expectedKey => $expectedValue
                ]
            ],
            $setB[1] => [
                $setB[2] => [
                    $expectedKey => $expectedValue
                ]
            ]
        ];

        $this->assertEquals($expected, $actual);
    }


    /**
     * Test that an attempt to retrieve an invalid payload throws an exception
     *
     *
     */
    public function testFailedFetch()
    {
        $this->expectException(\InvalidArgumentException::class);
        $trie = $this->newTrie('nothingSpecial');
        $trie->set([2, 4, 6], 'keykey', 'valval');

        $trie->fetch([ 4 ]);
    }
}
