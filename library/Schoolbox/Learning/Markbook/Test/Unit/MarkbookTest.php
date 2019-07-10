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
     * Data provider for `setupFirstQuartile`.
     * Dataset should already be sorted and cannot be null
     *
     * @return array
     */
    public function providerForTestSetupFirstQuartile()
    {
        return [
            [
                'marks' => [3, 5, 7, 8, 12],
                'expected' => [3, 5]
            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expected' => [1, 2, 3, 4, 5, 60, 70]

            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 92, 93, 94, 95, 100],
                'expected' => [1, 2, 3, 4, 5, 60, 70]

            ],
            [
                'marks' => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
                'expected' => [1, 1, 2, 2, 3]
            ],
            [
                'marks' => [3.4, 4.3, 6.9, 7.5],
                'expected' => [3.4, 4.3]
            ],
            [
                'marks' => [7.3, 7.3, 7.3, 7.3, 7.3],
                'expected' => [7.3, 7.3]
            ],
            [
                'marks' => [65, 85],
                'expected' => [65]
            ],
            [
                'marks' => [0, 0, 3, 50, 53, 61, 80, 83],
                'expected' => [0, 0, 3, 50]
            ]
        ];
    }


    /**
     * @dataProvider providerForTestSetupFirstQuartile
     *
     * @param $marks
     * @param $expected
     */
    public function testSetupFirstQuartile($marks, $expected)
    {
        $median = Markbook::findMedian($marks);
        $firstHalve = Markbook::setupFirstQuartile($marks, $median);
        $this->assertEquals($firstHalve, $expected);
    }

    /**
     * Data provider for `setupThirdQuartile`.
     * Dataset should already be sorted and cannot be null
     *
     * @return array
     */
    public function providerForTestSetupThirdQuartile()
    {
        return [
            [
                'marks' => [3, 5, 7, 8, 12],
                'expected' => [8, 12]
            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 92, 93, 94, 95, 100],
                'expected' => [85, 91, 92, 93, 94, 95, 100]

            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expected' => [91, 91, 92, 93, 94, 95, 100]

            ],
            [
                'marks' => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
                'expected' => [3, 4, 4, 5, 5]
            ],
            [
                'marks' => [3.4, 4.3, 6.9, 7.5],
                'expected' => [6.9, 7.5]
            ],
            [
                'marks' => [7.3, 7.3, 7.3, 7.3, 7.3],
                'expected' => [7.3, 7.3]
            ],
            [
                'marks' => [65, 85],
                'expected' => [85]
            ],
            [
                'marks' => [0, 0, 3, 50, 53, 61, 80, 83],
                'expected' => [53, 61, 80, 83]
            ]
        ];
    }

    /**
     * @dataProvider providerForTestSetupThirdQuartile
     *
     * @param $marks
     * @param $expected
     */
    public function testSetupThirdQuartile($marks, $expected)
    {
        $median = Markbook::findMedian($marks);
        $secondHalve = Markbook::setupThirdQuartile($marks, $median);
        $this->assertEquals($secondHalve, $expected);
    }

    /**
     * Data provider for `findMedian`.
     * Dataset should already be sorted and cannot be null
     *
     * @return array
     */
    public function providerForFindMedian()
    {
        return [
            [
                'marks' => [3, 5, 7, 8, 12],
                'expectedQuartile1' => 4,
                'expectedQuartile2' => 7,
                'expectedQuartile3' => 10
            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expectedQuartile1' => 4,
                'expectedQuartile2' => 85,
                'expectedQuartile3' => 93

            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 92, 93, 94, 95, 100],
                'expectedQuartile1' => 4,
                'expectedQuartile2' => 77.5,
                'expectedQuartile3' => 93

            ],
            [
                'marks' => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
                'expectedQuartile1' => 2,
                'expectedQuartile2' => 3,
                'expectedQuartile3' => 4
            ],
            [
                'marks' => [3.4, 4.3, 6.9, 7.5],
                'expectedQuartile1' => 3.85,
                'expectedQuartile2' => 5.6,
                'expectedQuartile3' => 7.2
            ],
            [
                'marks' => [7.3, 7.3, 7.3, 7.3, 7.3],
                'expectedQuartile1' => 7.3,
                'expectedQuartile2' => 7.3,
                'expectedQuartile3' => 7.3
            ],
            [
                'marks' => [65, 85],
                'expectedQuartile1' => 65,
                'expectedQuartile2' => 75,
                'expectedQuartile3' => 85,
            ],
            [
                'marks' => [0, 0, 3, 50, 53, 61, 80, 83],
                'expectedQuartile1' => 1.5,
                'expectedQuartile2' => 51.5,
                'expectedQuartile3' => 70.5,
            ]
        ];
    }

    /**
     * @dataProvider providerForFindMedian
     *
     * @param $marks
     * @param $expectedQuartile1
     * @param $expectedQuartile2
     * @param $expectedQuartile3
     */
    public function testFindMedian($marks, $expectedQuartile1, $expectedQuartile2, $expectedQuartile3)
    {

        $this->assertEquals(Markbook::findMedian($marks), $expectedQuartile2);
        $this->assertEquals(Markbook::findMedian(Markbook::setupFirstQuartile($marks)), $expectedQuartile1);
        $this->assertEquals(Markbook::findMedian(Markbook::setupThirdQuartile($marks)), $expectedQuartile3);
    }
}