<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Routing\Test;

use Alaress\Routing\Dictionary\SubscriptionDictionary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SubscriptionDictionaryTest extends TestCase
{

    /**
     * @return array
     */
    public static function dataInvalidConstruction()
    {
        return [
            [ '' ],
            [ null ]
        ];
    }


    /**
     * Test that a router cannot be constructed without a delimiter
     *
     *
     * @param string $delim
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataInvalidConstruction')]
    public function testInvalidConstruction($delim)
    {
        $this->expectException(InvalidArgumentException::class);
        new SubscriptionDictionary($delim);
    }


    /**
     * Test that the delimiter comes out as it went in (trivialities much?)
     */
    public function testGetDelimiter()
    {
        $expected = ':';

        $dic = new SubscriptionDictionary($expected);

        $this->assertEquals($expected, $dic->getDelimiter());
    }


    /**
     * @return array
     */
    public static function dataShardTranslation()
    {
        return [
            [ '42',      '(\d+)' ],
            [ '*',       '[^\ ]+' ],
            [ '#',       '.*' ],
            [ 'literal', 'literal' ]
        ];
    }


    /**
     *
     * @param string $shard
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataShardTranslation')]
    public function testShardTranslation($shard, $expected)
    {
        $dic = new SubscriptionDictionary(' ');

        $this->assertEquals($expected, $dic->translateShard($shard));
    }


    /**
     * @return array
     */
    public static function dataWholeTranslation()
    {
        $word = '[^\:]+';

        return [
            [ 'foo', 'foo' ],
            [ 'foo:bar', 'foo:bar' ],
            [ 'foo:bar:42', 'foo:bar:(\d+)' ],
            [ 'foo:bar:42:*', "foo:bar:(\\d+):$word" ],
            [ 'foo:bar:42:*:#', "foo:bar:(\\d+):$word:.*" ],
            [ 'foo:*', "foo:$word" ],
            [ 'foo:#', 'foo:.*' ],
        ];
    }


    /**
     *
     * @param string $whole
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataWholeTranslation')]
    public function testWholeTranslation($whole, $expected)
    {
        $dic = new SubscriptionDictionary(':');

        $this->assertEquals($expected, $dic->translateWhole($whole));
    }
}
