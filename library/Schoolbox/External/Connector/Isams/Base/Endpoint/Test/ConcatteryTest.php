<?php

/**
 * ConcatteryTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace External\Connector\Isams\Base\Endpoint\Test;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Concattery;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

class ConcatteryTest extends TestCase
{
    /**
     * A sample input slab of xml. We'll be parsing this xpath usage
     * in addition to concat string generation
     *
     * @var string
     */
    private string $xml = "<?xml version='1.0' encoding='utf-8'?>
<Stuff>
    <Entry>
        <Code>Marty O'Donnell</Code>
    </Entry>
    <Entry>
        <Code>Jack O'Neill</Code>
    </Entry>
        <Entry>
        <Code>Colonel Jonathon \"Jack\" O'Neill</Code>
    </Entry>
    <Entry>
        <Code>McDonald's</Code>
    </Entry>
    <Entry>
        <Code>What is \"this thing\"</Code>
    </Entry>
    <Entry>
        <Code>Only one double-quote \" lol</Code>
    </Entry>
    <Entry>
        <Code>Only one single-quote ' lol</Code>
    </Entry>
</Stuff>
";


    /**
     * Specifically test the xml slab
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function testXmlTraverse()
    {
        $xml = (new \SimpleXMLElement($this->xml));
        $cattery = new Concattery();

        $nodes = $xml->xpath('//Entry/Code');

        foreach ($nodes as $node) {
            $searchTerm = (string)$node[0];

            $concat = $cattery->generate($searchTerm);

            $outputNode = $xml->xpath("//Entry/Code[text()={$concat}]");

            /* There's exactly one instance of each $searchTerm in the xml slab
             */
            $this->assertEquals(1, \count($outputNode));
        }
    }


    /**
     * @dataProvider providerConcatString
     * @param mixed $input
     * @param int   $expected
     */
    public function testConcatString($input, $expected)
    {
        $concat = (new Concattery())->generate($input);

        $this->assertEquals($expected, $concat);

    }

    /**
     * Data provider for testConcatString
     *
     * @return array
     */
    public function providerConcatString(): array
    {
        return [
            'Single Quotes 1' => [
                "Marty O'Donnell",
                '"Marty O\'Donnell"'
            ],

            'Single Quotes 2' => [
                "Jack O'Neill",
                '"Jack O\'Neill"'
            ],

            'Single Quotes 3' => [
                "McDonald's",
                '"McDonald\'s"'
            ],

            'Lone double quote 1' => [
                'Only one double-quote " lol',
                'concat("Only one double-quote ", \'"\', " lol")'
            ],

            'Lone single quote 1' => [
                "Only one single-quote ' lol",
                '"Only one single-quote \' lol"'
            ],

            'Combined quotes 1' => [
                "Colonel Jonathon \"Jack\" O'Neill",
                'concat("Colonel Jonathon ", \'"\', "Jack", \'"\', " O\'Neill")'
            ]
        ];
    }
}
