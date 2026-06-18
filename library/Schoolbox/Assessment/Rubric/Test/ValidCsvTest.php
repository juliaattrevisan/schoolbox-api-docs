<?php

/**
 * ValidCsvTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\ValidCsv;

/**
 * ValidCsvTest
 */
class ValidCsvTest extends TestCase
{
    /**
     * @param mixed $input
     * @param bool   $expected
     */
    #[DataProvider('providerValidCsv')]
    public function testValidCsv($input, $expected)
    {
        /* Set up a pseudo-resource as cheaply and as simply as I can devise
         */
        $stream = fopen('php://memory', 'w+');

        if ($stream) {
            fwrite($stream, $input);
            rewind($stream);
        }

        $outcome = (new ValidCsv())->isSatisfiedBy($stream);

        fclose($stream);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidCsv
     *
     * @return array
     */
    public static function providerValidCsv(): array
    {
        return [
            /* These are valid
             */

            'no-levels-+-no-descriptors-rubric.csv' => [
'"capabilityMarkType: none","useMarkRange: Off",,,,,
"Approaches to Learning",Engaged,,,,,
,,4,3,2,1,
,Persistent,,,,,
,,4,3,2,1,
Knowledge,Chemistry,,,,,
,,5,4,3,2,1
,Biolody,,,,,
,,5,4,3,2,1
,Physics,,,,,
,,5,4,3,2,1',
                true
            ],

            'no-levels-+-no-points-rubric.csv' => [
'"capabilityMarkType: none","useMarkRange: Off",,,,,
"Approaches to Learning",Engaged,"Was highly engaged","Was mostly engaged","Was somewhat engaged","Was rarely engaged",
,,,,,,
,Persistent,"Was highly persistent","Was mostly persistent","Was somewhat persistent","Was rarely persistent",
,,,,,,
Knowledge,Chemistry,A,B,C,D,E
,,,,,,
,Biolody,A,B,C,D,E
,,,,,,
,Physics,A,B,C,D,E
,,,,,,',
                true
            ],

            'no-levels-+-no-descriptors-weird-alignments-rubric.csv' => [
'"capabilityMarkType: none","useMarkRange: Off",,,,,
"Approaches to Learning",Engaged,,,,,
,,,4,3,2,1
,Persistent,,,,,
,,,4,3,2,1
Knowledge,Chemistry,,,,,
,,5,4,3,2,1
,Biolody,,,,,
,,5,4,3,2,1
,Physics,,,,,
,,5,4,3,2,1',
                true,
            ],


            /* These are invalid
             */

            'Empty input' => [
                '',
                false,
            ],

            'Worktype csv' => [
'"Work Type","Display on Grades Page",Visible
"Learning Activity",No,staffAndStudentsAndParents
"Approach to Learning",Yes,staffAndStudentsAndParents
"Formative Assessment Task",No,staffAndStudentsAndParents
Reporting,No,staffAndStudentsAndParents
"Summative Assessment Task",Yes,staffAndStudentsAndParents
"Homework Task",No,staffAndStudentsAndParents
Observation,No,staffOnly
"Lesson Planning",No,staffOnly
"Staff only",No,staffOnly
"Staff & Students",No,staffOnly
"Staff & Parents",No,staffAndParents
"Staff, Students & Parents",No,staffAndStudentsAndParents',
                false
            ],

            'no capability' => [
'"capabilityMarkType: none","useMarkRange: Off"',
                false
            ],

            'no indicator' => [
'"capabilityMarkType: none","useMarkRange: Off",,,,,
"Approaches to Learning",,,,,,',
                false
            ],

            'no descriptor' => [
                '"capabilityMarkType: none","useMarkRange: Off",,,,,
"Approaches to Learning",Engaged,,,,,
,,,,,,
,,,,,,
,,,,,,
,,,,,,',
                false
            ],
        ];
    }
}
