<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules\Token\Test;


use Alaress\Rules\Lang\Map;
use PHPUnit\Framework\TestCase;

class TokenizerTest extends TestCase
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
            'foo'             => 'The foo',
            'bar'             => 'The bar',
            'baz.bar'         => "The baz's bar",
            'single.multi[]'  => "The single's multi",
            'multi[].multi[]' => "The multi's multi",
            'empty[]'         => "An empty multi",
            'alpha[].id'      => "The IDs of the alpha's bravos"
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
            'multi' => [
                [
                    'multi' => [
                        'first',
                        'second',
                    ]
                ],
                [
                    'multi' => [
                        'third',
                        'fourth',
                    ]
                ]
            ],
            'empty' => [],
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
            'missing mapped value'
                => [ new Map('my{:foo}', [ 'mydata2' => 'something else']), ''],
            'empty multi-value'
                => ['{:empty}', ''],
            'nested multi-value'
                => ['{:single.multi}', ['first', 'second', 'third', 'fourth']],
            'nested multi-value filter'
                => ['{:single.multi|first}', ['f', 's', 't']],
            'nested multi-value x multi-value'
                => ['{:single.multi|first}y{:single.multi}', [
                        'fyfirst','fysecond','fythird','fyfourth',
                        'syfirst','sysecond','sythird','syfourth',
                        'tyfirst','tysecond','tythird','tyfourth',
                    ]],
            'nested multi-value x empty'
                => ['{:single.multi}-{:empty}', ['first-', 'second-', 'third-', 'fourth-']],
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
            'nested multi-value subvalue extract out-of-range'
                => ['{:alpha.9.id}', ''],
            'multi-multi value'
                => ['{:multi.multi}', ['first', 'second', 'third', 'fourth']],
            'multi-multi value filter'
                => ['{:multi.multi|first}', ['f','s','t']],
            'multi-multi x multi-multi'
                => ['{:multi.multi|first}{:multi.multi}', [
                        'ffirst', 'fsecond', 'fthird', 'ffourth',
                        'sfirst', 'ssecond', 'sthird', 'sfourth',
                        'tfirst', 'tsecond', 'tthird', 'tfourth',
                    ]],
            'single-multi + single-multi'
            => ['{:single.multi}+{:single.multi}', [
                'first+first', 'second+second', 'third+third', 'fourth+fourth',
            ]],
            'multi-multi mapped'
                => [ new Map('{:multi.multi}', [
                        'first'  => 'ONE',
                        'second' => 'TWO',
                        'third'  => 'THREE',
                        'fourth' => 'FOUR'
                   ]), [ 'ONE', 'TWO', 'THREE', 'FOUR' ] ],
            /*
             * KNOWN FAIL CASE:
             * attempting to combine an array field against a replica of itself
             * (eg. "{:single.multi}x{:single.multi}") will result in a 'natural join' rather than 'cartesian'
             *
             * Buggy behaviour is observed when the token strings are identical: using a filter on one resolves the issue.
             * */
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
