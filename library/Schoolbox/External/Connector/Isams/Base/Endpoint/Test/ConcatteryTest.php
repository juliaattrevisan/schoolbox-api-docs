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


//
//    /**
//     * @dataProvider providerValidExample
//     * @param mixed $input
//     * @param int   $expected
//     */
//    public function testValidExample($input, $expected)
//    {
//        /* Substitute this with a spec pattern or other conditional
//         */
//        $concattery = new Concattery();
//
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
//     * Data provider for testValidExample
//     *
//     * @return array
//     */
//    public function providerValidExample(): array
//    {
//        return [
//
//            /* Add other cases that you expect to be valid
//             */
//            'Valid Example 1' => [
//                0,    // goes into testValidExample() as $input
//                true  // goes into testValidExample() as $expected
//            ],
//
//            'Valid Example 2' => [
//                1,
//                true
//            ],
//
//            'Valid Example 3' => [
//                123456789,
//                true
//            ],
//
//            'Valid Example 4' => [
//                '1e4',
//                true
//            ],
//
//            /* Add other cases that you expect to be invalid
//             */
//            'Invalid Example 1' => [
//                false,
//                false
//            ],
//
//            'Invalid Example 2' => [
//                'a',
//                false
//            ],
//
//            'Invalid Example 3' => [
//                null,
//                false
//            ],
//
//            'Invalid Example 4' => [
//                '',
//                false
//            ],
//        ];
//    }

}
