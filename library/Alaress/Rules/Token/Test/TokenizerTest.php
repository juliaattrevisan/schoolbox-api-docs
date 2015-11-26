<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Token\Test;


use Alaress\Rules\Lang\Map;
use PHPUnit_Framework_TestCase;

class TokenizerTest extends PHPUnit_Framework_TestCase
{
    /** @var MockTokenizer */
    protected $tokenizer;

    /** @var array */
    protected $tree;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     */
    protected function setUp()
    {
        $this->tokenizer = new MockTokenizer([
            'foo' => 'The foo',
            'bar' => 'The bar',
            'baz.bar' => "The baz's bar",
        ]);

        $this->tree = [
            'foo' => 'data1',
            'bar' => 'data2',
            'baz' => [
                'bar' => 'data3'
            ],
        ];
    }


    /**
     * @return array
     */
    public function tokenParsingData()
    {
        return [
            [ 'bar',                    'bar'                             ],
            [ '{:bar}',                 'data2'                           ],
            [ '{:bar}',                 'data2'                           ],
            [ '{:baz.bar}',             'data3'                           ],
            [ 'a{:foo}-{:bar}b',        'adata1-data2b'                   ],
            [ 'a{:foo}-{:bam}b',        'adata1-b'                        ],
            [ '{:baz.bar|first}',       'd'                               ],
            [ 'a{:foo|first}',          'ad'                              ],
            [ new Map('my{:foo}', [
                'mydata1' => 'something else'
            ]),                         'something else'                  ],
        ];
    }


    /**
     * @dataProvider tokenParsingData
     * @param string $token
     * @param mixed $expected
     */
    public function testTokenParsing($token, $expected)
    {
        $actual = $this->tokenizer->parseTokens([], '', $token, $this->tree);

        $this->assertEquals($expected, $actual);
    }

}
