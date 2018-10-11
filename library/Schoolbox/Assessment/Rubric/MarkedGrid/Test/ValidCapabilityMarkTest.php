<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\IMarkScheme;

class ValidCapabilityMarkTest extends TestCase
{

    public function getTestIsSatisfiedByData() : iterable
    {
        $capabilityMax1 = 5;

        $markScheme1 = new Percentage();

        $capabilityMark1 = 6;


        yield [$capabilityMax1, $markScheme1, $capabilityMark1, true];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark::isSatisfiedBy
     *
     * @param float $capabilityMax
     * @param IMarkScheme $markScheme
     * @param $capabilityMark
     * @param bool $expected                 Whether this test should assert true or false
    */
    public function testIsSatisfiedBy(float $capabilityMax, IMarkScheme $markScheme, $capabilityMark, bool $expected)
    {
        $spec = new ValidCapabilityMark($capabilityMax, $markScheme);
        $this->assertTrue($spec->isSatisfiedBy($capabilityMark));
    }
}
