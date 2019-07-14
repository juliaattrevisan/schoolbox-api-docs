<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Analytics\BoxPlot\Test\Unit;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Analytics\BoxPlot\Quartile;


/**
 * Tests for Quartile methods
 */
class QuartileTest extends TestCase
{

    /**
     * Data provider for `setupFirstQuartile`.
     *
     * @return array
     */
    public function providerForTestSetupFirstQuartile()
    {
        return [
            [
                'marks' => [],
                'expected' => [],
            ],
            [
                'marks' => [null],
                'expected' => [],
            ],
            [
                'marks' => [null, null, null, 'null', 'sadf', '234', '23.234'],
                'expected' => [],
            ],
            [
                'marks' => [null, 'null', 1.3, 2, 3, 4, 5, '234', null, 0, '1e3'],
                'expected' => [0, 1.3, 2],
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
            ],
            [
                'marks' => [3.343, 5.2, 0, 0, null, null, 1000, 3, 50, 53, 61, 80, 83],
                'expected' => [0, 0, 3, 3.343, 5.2]
            ],
            [
                'marks' => [9.3],
                'expected' => [9.3]
            ],
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
        $quartile = new Quartile();
        $firstHalve = $quartile->setupFirstQuartile($marks);
        $this->assertEquals($firstHalve, $expected);
    }

    /**
     * Data provider for `setupThirdQuartile`.
     *
     * @return array
     */
    public function providerForTestSetupThirdQuartile()
    {
        return [
            [
                'marks' => [],
                'expected' => [],
            ],
            [
                'marks' => [null],
                'expected' => [],
            ],
            [
                'marks' => [null, null, null, 'null', 'sadf', '234', '23.234'],
                'expected' => [],
            ],
            [
                'marks' => [null, 'null', 1.3, 2, 3, 4, 5, '234', null, 0, '1e3'],
                'expected' => [3, 4, 5],
            ],
            [
                'marks' => [3, 5, 7, 8, 12],
                'expected' => [8, 12]
            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expected' => [91, 91, 92, 93, 94, 95, 100]

            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 92, 93, 94, 95, 100],
                'expected' => [85, 91, 92, 93, 94, 95, 100]

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
                'marks' => [3.343, 5.2, 0, 0, null, null, 1000, 3, 50, 53, 61, 80, 83],
                'expected' => [53, 61, 80, 83, 1000]
            ],
            [
                'marks' => [9.3],
                'expected' => [9.3]
            ],
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
        $quartile = new Quartile();
        $secondHalve = $quartile->setupThirdQuartile($marks);
        $this->assertEquals($secondHalve, $expected);
    }

    /**
     * Data provider for `findMedian`.
     *
     * @return array
     */
    public function providerForFindMedian()
    {
        return [
            [
                'marks' => [],
                'expectedQuartile1' => null,
                'expectedQuartile2' => null,
                'expectedQuartile3' => null,
            ],
            [
                'marks' => [null],
                'expectedQuartile1' => null,
                'expectedQuartile2' => null,
                'expectedQuartile3' => null,
            ],
            [
                'marks' => [null, null, null, 'null', 'sadf', '234', '23.234'],
                'expectedQuartile1' => null,
                'expectedQuartile2' => null,
                'expectedQuartile3' => null,
            ],
            [
                'marks' => [null, 'null', 1.3, 2, 3, 4, 5, '234', null, 0, '1e3'],
                'expectedQuartile1' => 1.3,
                'expectedQuartile2' => 2.5,
                'expectedQuartile3' => 4,
            ],
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
                'marks' => [3.343, 5.2, 0, 0, null, null, 1000, 3, 50, 53, 61, 80, 83],
                'expectedQuartile1' => 3,
                'expectedQuartile2' => 50,
                'expectedQuartile3' => 80,
            ],
            [
                'marks' => [9.3],
                'expectedQuartile1' => 9.3,
                'expectedQuartile2' => 9.3,
                'expectedQuartile3' => 9.3,
            ],
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
        $quartile = new Quartile();

        $this->assertEquals($quartile->findMedian($marks), $expectedQuartile2);
        $this->assertEquals($quartile->findMedian($quartile->setupFirstQuartile($marks)), $expectedQuartile1);
        $this->assertEquals($quartile->findMedian($quartile->setupThirdQuartile($marks)), $expectedQuartile3);
    }
}