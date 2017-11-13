<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
    public function dataInvalidConstruction()
    {
        return [
            [ '' ],
            [ null ]
        ];
    }


    /**
     * Test that a router cannot be constructed without a delimiter
     *
     * @expectedException InvalidArgumentException
     * @dataProvider dataInvalidConstruction
     * @param string $delim
     */
    public function testInvalidConstruction($delim)
    {
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
    public function dataShardTranslation()
    {
        return [
            [ '42',      '(\d+)' ],
            [ '*',       '[^\ ]+' ],
            [ '#',       '.*' ],
            [ 'literal', 'literal' ]
        ];
    }


    /**
     * @dataProvider dataShardTranslation
     *
     * @param string $shard
     * @param string $expected
     */
    public function testShardTranslation($shard, $expected)
    {
        $dic = new SubscriptionDictionary(' ');

        $this->assertEquals($expected, $dic->translateShard($shard));
    }


    /**
     * @return array
     */
    public function dataWholeTranslation()
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
     * @dataProvider dataWholeTranslation
     *
     * @param string $whole
     * @param string $expected
     */
    public function testWholeTranslation($whole, $expected)
    {
        $dic = new SubscriptionDictionary(':');

        $this->assertEquals($expected, $dic->translateWhole($whole));
    }

}
