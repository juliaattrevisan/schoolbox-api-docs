<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Util;

/**
 * Test for rubric utils.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Util::class)]
class UtilTest extends TestCase
{
    /**
     * Data provider for `testNormalizeName`.
     *
     * @return iterable
     */
    public static function getTestNormalizeNameData(): iterable
    {
        return [
            'same'             => ['123', '123'],
            'left trim'        => ['123', ' 123'],
            'right trim'       => ['123', '123 '],
            'l+r trim'         => ['123', ' 123 '],
            'trim multiple'    => ['123', '       123       '],
            'normalize spaces' => ['123 456', '123  456'],
            'normalize break'  => ['123 456', "123\n\r456"],
            'normalize case'   => ['abcabc', 'ABCabc'],
        ];
    }

    /**
     * @param string $expected
     * @param string $name
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestNormalizeNameData')]
    public function testNormalizeName(string $expected, string $name): void
    {
        $this->assertSame($expected, Util::normalizeName($name));
    }
}
