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
            'foo'            => 'The foo',
            'bar'            => 'The bar',
            'baz.bar'        => "The baz's bar",
            'single.multi'   => "The single's multi",
            'alpha.bravo.id' => "The IDs of the alpha's bravos"
        ]);

        $this->tree = [
            'foo' => 'data1',
            'bar' => 'data2',
            'baz' => [
                'bar' => 'data3'
            ],
            'single' => [
                'multi' => [
                    'first',
                    'second',
                    'third',
                    'fourth'
                ]
            ],
            'alpha' => [
                'bravo'   => [ 'id' => 'B' ],
                'charlie' => [ 'id' => 'C' ],
                'delta'   => [ 'id' => 'D' ],
                'echo'    => [ 'id' => 'E' ],
                'foxtrot' => [ 'id' => 'F' ]
            ]
        ];
    }


    /**
     * @return array
     */
    public function tokenParsingData()
    {
        return [
            'literal'
                => ['bar', 'bar'],
            'simple'
                => ['{:bar}', 'data2'],
            'different simple'
                => ['{:bar}', 'data2'],
            'nested value'
                => ['{:baz.bar}', 'data3'],
            'multi-token'
                => ['a{:foo}-{:bar}b', 'adata1-data2b'],
            'missing token'
                => ['a{:foo}-{:bam}b', 'adata1-b'],
            'filter'
                => ['{:baz.bar|first}', 'd'],
            'concat filter'
                => ['a{:foo|first}', 'ad'],
            'mapped'
                => [ new Map('my{:foo}', [ 'mydata1' => 'something else']), 'something else'],
            'nested multi-value'
                => ['{:single.multi}', ['first', 'second', 'third', 'fourth']],
            'nested mutli-value extract'
                => ['{:single.multi[1]}', 'second'],
            'nested multi-value extract dot-notation'
                => ['{:single.multi.2}', 'third'],
            'nested multi-value subvalue'
                => ['{:alpha.bravo.id}', ['B', 'C', 'D', 'E', 'F']],
            'nested multi-value subvalue extract'
                => ['{:alpha.bravo[2].id}', 'D'],
            'nested multi-value subvalue extract dot-notation'
                => ['{:alpha.bravo.4.id}', 'F'],
        ];
    }


    /**
     * @dataProvider tokenParsingData
     * @param string $token
     * @param mixed $expected
     */
    public function testTokenParsing($token, $expected)
    {
        $actual = $this->tokenizer->parseSimpleToken($this->tree, $token);

        $this->assertEquals($expected, $actual);
    }

}
