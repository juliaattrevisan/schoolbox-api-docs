<?php

/**
 * CustomStyleExtractorTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\SummativeReport\CustomStyleExtractor;

/**
 * Check that the custom style extractor can get the relevant css rules
 * from any arbitrary slab of text. It does not try to correct your css,
 * simply find and reimplement them as they are
 */
class CustomStyleExtractorTest extends TestCase
{
    /**
     * Some sample css that contains custom .summative-report styles
     */
    private const CSS_SLAB_1_INPUT = "
    th.rb-final-summary {
        padding: 0.5rem;
    }

    /* Rubric heading cells for Capabilities, Indicators, Levels
     * Use a lighter shade of grey than selected Descriptors
     */
    .rb th {
        background-color: #ececec;
    }

    /* Capabilities can optionally have a mark
     */
    .rb th.rb-group-summary {
        padding: 0.33rem;
        font-size: 1rem;
        text-align: right;
    }.summative-report p{font-color:#f00;clear:both;
    cat: 'meow'}
    .summative-report{
    dog:woof
    }
    /* Top-left cell is a meaningless cell
     */
    .rb.markable thead tr th:first-child {
        background-color: transparent;
        border-left: 0;
        border-top: 0;
    }
.summative-report.horse.cow   > elephant:first-child(9)  { neigh; moo''}
    /* Contents of capabilities, indicators, descriptors can have markdown
     */
    .markdown li, .markdown p {
        white-space: pre-wrap;
    }

    /* Apply measured margins to report. You can adjust those to suit
     * tested printouts to ensure you don't print over edges and stuff
     */
    @page {
        size: A4 portrait; /* try and enforce portrait orientation */
        margin: 0.5in 0.5in 0.75in 0.5in;
    }
    ";


    /**
     * This css should be extracted
     */
    private const CSS_SLAB_1_OUTPUT = ".summative-report p{font-color:#f00;clear:both;
    cat: 'meow'}
.summative-report{
    dog:woof
    }
.summative-report.horse.cow   > elephant:first-child(9)  { neigh; moo''}";


    /**
     * This doesn't have any summative-report styles
     */
    private const CSS_SLAB_2_INPUT = "
    /* Top-left cell is a meaningless cell
     */
    .rb.markable thead tr th:first-child {
        background-color: transparent;
        border-left: 0;
        border-top: 0;
    }
    /* Contents of capabilities, indicators, descriptors can have markdown
     */
    .markdown li, .markdown p {
        white-space: pre-wrap;
    }

    /* Apply measured margins to report. You can adjust those to suit
     * tested printouts to ensure you don't print over edges and stuff
     */
    @page {
        size: A4 portrait; /* try and enforce portrait orientation */
        margin: 0.5in 0.5in 0.75in 0.5in;
    }
    ";


    /**
     * @param string $input
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidCustomStyleExtraction')]
    public function testValidCustomStyleExtraction(string $input, $expected)
    {
        $outcome = (new CustomStyleExtractor())->extract($input);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidCustomStyleExtraction
     *
     * @return array
     */
    public static function providerValidCustomStyleExtraction(): array
    {
        return [
            /* Empty string in Skin Config
             */
            'Valid Extraction 1' => [
                '',
                ''
            ],

            /* Probable common use case
             */
            'Valid Extraction 2' => [
                self::CSS_SLAB_1_INPUT,
                self::CSS_SLAB_1_OUTPUT
            ],

            /* No summative-report styles in this input
             */
            'Valid Extraction 3' => [
                self::CSS_SLAB_2_INPUT,
                ''
            ],
        ];
    }
}
