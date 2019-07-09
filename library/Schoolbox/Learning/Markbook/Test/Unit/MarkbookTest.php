<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook\Test\Unit;

use PHPUnit\Framework\TestCase;

use Schoolbox\Learning\Markbook\Markbook;


/**
 * Tests for Markbook methods
 */
class MarkbookTest extends TestCase
{

    /**
     * Array of all students' marks. It should already be sorted
     * also should not be null or with one item in it.so these
     * are wrong values for testing: [5,2,10,3] / [] / [3]
     */
    private $marks = [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100];

    /**
     * First halve of the sutdents' marks for calculating the 1st quartile
     */
    private $marksFirstHalve = [1, 2, 3, 4, 5, 60, 70, 85];

    /**
     * Second halve of the sutdents' marks for calculating the 3rd quartile
     */
    private $marksSecondHalve = [85, 91, 91, 92, 93, 94, 95, 100];

    /**
     * First quartile
     */
    private $quartile1 = 4.5;

    /**
     * second quartile
     */
    private $quartile2 = 85;

    /**
     * Third quartile
     */
    private $quartile3 = 92.5;

    /**
     * Checks correctness of the first halve
     */
    public function testSetupFirstQuartile()
    {

        $median = Markbook::findMedian($this->marks);
        $firstHalve = Markbook::setupFirstQuartile($this->marks, $median);
        $this->assertEquals($firstHalve, $this->marksFirstHalve);
    }

    /**
     * Checks correctness of the second halve
     */
    public function testSetupThirdQuartile()
    {
        $median = Markbook::findMedian($this->marks);
        $secondHalve = Markbook::setupThirdQuartile($this->marks, $median);
        $this->assertEquals($secondHalve, $this->marksSecondHalve);
    }

    /**
     * Checks correctness of the calculate median value
     * for all marks, first halve and second halve
     */
    public function testFindMedian()
    {

        $this->assertEquals(Markbook::findMedian($this->marks), $this->quartile2);
        $this->assertEquals(Markbook::findMedian($this->marksFirstHalve), $this->quartile1);
        $this->assertEquals(Markbook::findMedian($this->marksSecondHalve), $this->quartile3);
    }
}
