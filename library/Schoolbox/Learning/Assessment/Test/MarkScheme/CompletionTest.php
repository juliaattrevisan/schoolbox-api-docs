<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Completion;
use \InvalidArgumentException;
use \RangeException;

class CompletionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetHumanReadable_Null()
    {
        $normalised = null;
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException RangeException
     */
    public function test_GetHumanReadable_TooLow()
    {
        $normalised = -1;
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetHumanReadable_InvalidType()
    {
        $normalised = 'zero';
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetHumanReadable_Invalid()
    {
        $normalised = '50.0';
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($normalised);
    }


    /**
     * @dataProvider provider_normalisedToHumanReadable
     * @param float $normalised
     * @param string $expected
     */
    public function test_GetHumanReadable_Valid($normalised, $expected)
    {
        $markScheme = new Completion();
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetNormalised_Null()
    {
        $humanReadable = null;
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetNormalised_Empty()
    {
        $humanReadable = '';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetNormalised_Whitespace()
    {
        $humanReadable = '     ';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_GetNormalised_Invalid()
    {
        $humanReadable = 'some_unknown_human_readable_value';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($humanReadable);
    }


    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float $expected
     */
    public function test_GetNormalised_Valid($humanReadable, $expected)
    {
        $markScheme = new Completion();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    // ==================== Data Providers ====================

    public function provider_normalisedToHumanReadable()
    {
        return [
            [Completion::NORMALISED_INCOMPLETE, Completion::HUMAN_READABLE_INCOMPLETE],
            [Completion::NORMALISED_COMPLETE,   Completion::HUMAN_READABLE_COMPLETE],
        ];
    }

    public function provider_humanReadableToNormalised()
    {
        return [
            [Completion::HUMAN_READABLE_INCOMPLETE, IMarkScheme::NORMALISED_MARK_MIN],
            [Completion::HUMAN_READABLE_COMPLETE,   IMarkScheme::NORMALISED_MARK_MAX],
        ];
    }


}