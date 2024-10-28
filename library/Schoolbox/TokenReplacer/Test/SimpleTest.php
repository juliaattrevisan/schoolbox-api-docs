<?php

/**
 * SimpleTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\TokenReplacer\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\TokenReplacer\Simple;

/**
 * Class SimpleTest
 *
 * @package Schoolbox\TokenReplacer\Test
 */
class SimpleTest extends TestCase
{
    /**
     * Test the token detection mechanism
     *
     *
     * @param string $input
     * @param array  $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerManifest')]
    public function testManifest(string $input, array $expected): void
    {
        $outcome = (new Simple())->manifest($input);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testSimpleManifest
     *
     * @return array
     */
    public static function providerManifest(): array
    {
        return [
            'No Tokens' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                []
            ],

            'One token, start' => [
                "[firstname]. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                ['[firstname]']
            ],

            'One token, middle' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                ['[firstname]']
            ],

            'One token, end' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and blah at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.[firstname]",
                ['[firstname]']
            ],

            'Multiple tokens' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of destination. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [token2] carried on an airplane.[lastname]",
                ['[token1]', '[token4]', '[firstname]', '[token3]', '[token2]', '[lastname]']
            ],

            'Multiple tokens duplicated' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of [token4]. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname] carried on an airplane.[firstname]",
                ['[token1]', '[token4]', '[firstname]', '[token3]']
            ],

            'Multiple tokens duplicated, misc characters' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                ['[tok@#%*(&en1]', '[__918]', '[[[[fox]', '[.cat]', '[   token3]', '[firstname   ]', '[firstname]']
            ],
        ];
    }


    /**
     *
     * @param string $input
     * @param array  $replacements
     * @param string $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReplace')]
    public function testReplace(string $input, array $replacements, string $expected): void
    {
        $outcome = (new Simple())->replace($input, $replacements);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testSimple
     *
     * @return array
     */
    public static function providerReplace(): array
    {
        return [
            'Replacement: No Tokens' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane."
            ],

            'Replacement: One token, start' => [
                "[firstname]. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => 'John'
                ],
                "John. a list of the cargo carried by a ship, made for the use of
                various agents and John at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane."
            ],

            'Replacement: One token, middle' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => 'John'
                ],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and John at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane."
            ],

            'Replacement: One token, end' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and blah at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.[firstname]",
                [
                    '[firstname]' => 'John'
                ],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and blah at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.John",
            ],

            'Replacement: Multiple tokens' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of destination. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [token2] carried on an airplane.[lastname]",
                [
                    '[token1]' => 'Robert',
                    '[token4]' => 'En taro Adun',
                    '[firstname]' => 'my life for Aiur',
                    '[token3]' => 'You need to build additional pylons',
                    '[token2]' => 'Explorer reporting!',
                    '[lastname]' => 'cat'
                ],
                "Robert. a list of the cargo carried En taro Adun a ship, made for the use of
                various agents and my life for Aiur at the ports of destination. a You need to build additional pylons or
                invoice of goods transported by truck or train. a list of the cargo
                or Explorer reporting! carried on an airplane.cat"
            ],

            'Replacement: Multiple tokens duplicated' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of [token4]. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname] carried on an airplane.[firstname]",
                [
                    '[token1]' => 'Robert',
                    '[token4]' => 'En taro Adun',
                    '[firstname]' => 'my life for Aiur',
                    '[token3]' => 'You need to build additional pylons',
                ],
                "Robert. a list of the cargo carried En taro Adun a ship, made for the use of
                various agents and my life for Aiur at the ports of En taro Adun. a You need to build additional pylons or
                invoice of goods transported by truck or train. a list of the cargo
                or my life for Aiur carried on an airplane.my life for Aiur",
            ],

            'Replacement: Multiple tokens duplicated, misc characters' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
                "Liara. a list of the cargo carried Shepard a ship, made for the use of
                various agents and Wrex at the ports of Grunt. a Garrus or
                invoice of goods transported by truck or train. a list of the cargo
                or Tali carried on an airplane.someone else might have gotten it wrong",
            ],

            /* More esoteric cases
             */
            'Replacement: More tokens than replacements 1' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                [],
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]"
            ],

            'Replacement: More tokens than replacements 2' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                [
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.someone else might have gotten it wrong"
            ],

            'Replacement: More replacements than tokens 1' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
            ],

            'Replacement: More replacements than tokens 2' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the [__918] of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the Shepard of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
            ],

            /* HTML shenanigans
             */
            'HTML 1' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the [firstname] [lastname]. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => '<strong>Bro</strong>',
                    '[lastname]' => '<em>what is this</em>'
                ],
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the &lt;strong&gt;Bro&lt;/strong&gt; &lt;em&gt;what is this&lt;/em&gt;. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
            ],
            'HTML 2: No tokens' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the port of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [],
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the port of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>"
            ],
            'HTML 3: With mention highlight' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\" data-mention=\"[firstname]\">[firstname]</span>
                <span class=\"mention\" data-mention=\"[lastname]\">[lastname]</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                    '[lastname]' => 'what is this'
                ],
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the Brother
                what is this. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>"
            ],
            'HTML 4: Mention highlight without span' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <em class=\"mention\" data-mention=\"[firstname]\">[firstname]</em>
                <em class=\"mention\" data-mention=\"[lastname]\">[lastname]</em>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                    '[lastname]' => 'what is this'
                ],
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <em class=\"mention\" data-mention=\"Brother\">Brother</em>
                <em class=\"mention\" data-mention=\"what is this\">what is this</em>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>"
            ],
            'HTML 5: Incomplete mention span tag' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\">[firstname]</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                ],
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\">Brother</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>"
            ],
        ];
    }

    /**
     * @param string $body
     * @param string $replacedBody
     * @param array  $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerExtract')]
    public function testExtract(string $body, string $replacedBody, array $expected): void
    {
        $outcome = (new Simple())->extract($body, $replacedBody);
        $this->assertSame($expected, $outcome);
    }

    /**
     * Data provider for `testExtract`
     *
     * @return iterable
     */
    public static function providerExtract(): iterable
    {
        return [
            'Replacement: No Tokens' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [],
            ],

            'Replacement: One token, start' => [
                "[firstname]. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "John. a list of the cargo carried by a ship, made for the use of
                various agents and John at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => 'John'
                ],
            ],

            'Replacement: One token, middle' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and [firstname] at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and John at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => 'John'
                ],
            ],

            'Replacement: One token, end' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and blah at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.[firstname]",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and blah at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.John",
                [
                    '[firstname]' => 'John'
                ],
            ],

            'Replacement: Multiple tokens' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of destination. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [token2] carried on an airplane.[lastname]",
                "Robert. a list of the cargo carried En taro Adun a ship, made for the use of
                various agents and my life for Aiur at the ports of destination. a You need to build additional pylons or
                invoice of goods transported by truck or train. a list of the cargo
                or Explorer reporting! carried on an airplane.cat",
                [
                    '[token1]' => 'Robert',
                    '[token4]' => 'En taro Adun',
                    '[firstname]' => 'my life for Aiur',
                    '[token3]' => 'You need to build additional pylons',
                    '[token2]' => 'Explorer reporting!',
                    '[lastname]' => 'cat'
                ],
            ],

            'Replacement: Multiple tokens duplicated' => [
                "[token1]. a list of the cargo carried [token4] a ship, made for the use of
                various agents and [firstname] at the ports of [token4]. a [token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname] carried on an airplane.[firstname]",
                "Robert. a list of the cargo carried En taro Adun a ship, made for the use of
                various agents and my life for Aiur at the ports of En taro Adun. a You need to build additional pylons or
                invoice of goods transported by truck or train. a list of the cargo
                or my life for Aiur carried on an airplane.my life for Aiur",
                [
                    '[token1]' => 'Robert',
                    '[token4]' => 'En taro Adun',
                    '[firstname]' => 'my life for Aiur',
                    '[token3]' => 'You need to build additional pylons',
                ],
            ],

            'Replacement: Multiple tokens duplicated, misc characters' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                "Liara. a list of the cargo carried Shepard a ship, made for the use of
                various agents and Wrex at the ports of Grunt. a Garrus or
                invoice of goods transported by truck or train. a list of the cargo
                or Tali carried on an airplane.someone else might have gotten it wrong",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
            ],

            /* More esoteric cases
             */
            'Replacement: More tokens than replacements 1' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                [],
            ],

            'Replacement: More tokens than replacements 2' => [
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.[firstname]",
                "[tok@#%*(&en1]. a list of the cargo carried [__918] a ship, made for the use of
                various agents and [[[[fox] at the ports of [.cat]. a [   token3] or
                invoice of goods transported by truck or train. a list of the cargo
                or [firstname   ] carried on an airplane.someone else might have gotten it wrong",
                [
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
            ],

            'Replacement: More replacements than tokens 1' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the ports of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
            ],

            'Replacement: More replacements than tokens 2' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the [__918] of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the Shepard of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[tok@#%*(&en1]' => 'Liara',
                    '[__918]' => 'Shepard',
                    '[[[[fox]' => 'Wrex',
                    '[.cat]' => 'Grunt',
                    '[   token3]' => 'Garrus',
                    '[firstname   ]' => 'Tali',
                    '[firstname]' => 'someone else might have gotten it wrong'
                ],
            ],

            /* HTML shenanigans
             */
            'HTML 1' => [
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the [firstname] [lastname]. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                "noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the &lt;strong&gt;Bro&lt;/strong&gt; &lt;em&gt;what is this&lt;/em&gt;. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.",
                [
                    '[firstname]' => '<strong>Bro</strong>',
                    '[lastname]' => '<em>what is this</em>'
                ],
            ],
            'HTML 2: No tokens' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the port of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the port of destination. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [],
            ],
            'HTML 3: With mention highlight' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\" data-mention=\"[firstname]\">[firstname]</span>
                <span class=\"mention\" data-mention=\"[lastname]\">[lastname]</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the Brother
                what is this. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                    '[lastname]' => 'what is this'
                ],
            ],
            'HTML 4: Mention highlight without span' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <em class=\"mention\" data-mention=\"[firstname]\">[firstname]</em>
                <em class=\"mention\" data-mention=\"[lastname]\">[lastname]</em>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <em class=\"mention\" data-mention=\"Brother\">Brother</em>
                <em class=\"mention\" data-mention=\"what is this\">what is this</em>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                    '[lastname]' => 'what is this'
                ],
            ],
            'HTML 5: Incomplete mention span tag' => [
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\">[firstname]</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                "<p>noun. a list of the cargo carried by a ship, made for the use of
                various agents and officials at the <span class=\"mention\">Brother</span>. a list or
                invoice of goods transported by truck or train. a list of the cargo
                or passengers carried on an airplane.</p>",
                [
                    '[firstname]' => 'Brother',
                ],
            ],
        ];
    }
}
