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
     * @dataProvider providerBodyManifest
     *
     * @param string $input
     * @param array  $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testSimpleManifest(string $input, array $expected): void
    {
        $outcome = (new Simple())->manifest($input);

        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testSimpleManifest
     *
     * @return array
     */
    public function providerBodyManifest(): array
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
//
//
//    /**
//     * @dataProvider providerBody
//     *
//     * @param string $input
//     * @param string $expected
//     * @throws \PHPUnit\Framework\ExpectationFailedException
//     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
//     */
//    public function testSimple(string $input, string $expected): void
//    {
//
//        /* Substitute this with a spec pattern or other conditional
//         */
//        $outcome = false;
//
//        if (is_numeric($input)) {
//            $outcome = true;
//        }
//
//        /* There are other assertions that you can try (ctrl-click to follow)
//         */
//        $this->assertEquals($expected, $outcome);
//    }
//
//    /**
//     * Data provider for testSimple
//     *
//     * @return array
//     */
//    public function providerBody(): array
//    {
//        return [
//
//        ];
//    }
}
