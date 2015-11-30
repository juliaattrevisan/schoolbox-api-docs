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
            'single.multi[]' => "The single's multi",
            'alpha[].id'     => "The IDs of the alpha's bravos"
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
                [ 'id' => 'bravo' ],
                [ 'id' => 'charlie' ],
                [ 'id' => 'delta' ],
                [ 'id' => 'echo' ],
                [ 'id' => 'foxtrot' ]
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
            'chained filters'
                => ['a{:foo|first|uc}', 'aD'],
            'mapped'
                => [ new Map('my{:foo}', [ 'mydata1' => 'something else']), 'something else'],
            'nested multi-value'
                => ['{:single.multi}', ['first', 'second', 'third', 'fourth']],
            'nested multi-value filter'
                => ['{:single.multi|first}', ['f', 's', 't', 'f']],
            'nested multi-value x multi-value'
                => ['{:single.multi|first}y{:single.multi}', [
                        'fyfirst','fysecond','fythird','fyfourth',
                        'syfirst','sysecond','sythird','syfourth',
                        'tyfirst','tysecond','tythird','tyfourth',
                        'fyfirst','fysecond','fythird','fyfourth',
                    ]],
            'nested mutli-value extract'
                => ['{:single.multi[1]}', 'second'],
            'nested multi-value extract dot-notation'
                => ['{:single.multi.2}', 'third'],
            'nested multi-value subvalue'
                => ['{:alpha.id}', ['bravo', 'charlie', 'delta', 'echo', 'foxtrot']],
            'nested multi-value subvalue filter'
                => ['{:alpha.id|first}', ['b', 'c', 'd', 'e', 'f']],
            'nested multi-value subvalue extract'
                => ['{:alpha[2].id}', 'delta'],
            'nested multi-value subvalue extract filter'
                => ['{:alpha[2].id|first}', 'd'],
            'nested multi-value subvalue extract dot-notation'
                => ['{:alpha.4.id}', 'foxtrot'],
        ];
    }


    /**
     * @dataProvider tokenParsingData
     * @param string $token
     * @param mixed $expected
     */
    public function testTokenParsing($token, $expected)
    {
        $actual = $this->tokenizer->parseToken($this->tree, $token);

        $this->assertEquals($expected, $actual);
    }

}
