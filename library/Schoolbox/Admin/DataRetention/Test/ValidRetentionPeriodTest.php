<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\ValidRetentionPeriod;

class ValidRetentionPeriodTest extends TestCase
{
    #[DataProvider('providerIsSatisfiedBy')]
    public function testIsSatisfiedBy(mixed $input, bool $expected): void
    {
        $spec = new ValidRetentionPeriod();

        $this->assertSame($expected, $spec->isSatisfiedBy($input));
    }

    public static function providerIsSatisfiedBy(): array
    {
        return [
            'null (default retention period)' => [ // label
                null, // input
                true // expected value (output)
            ],
            'int 1' => [
                1,
                true
            ],
            'int 30' => [
                30,
                true
            ],
            'string 1' => [
                '1',
                true
            ],
            'string 365' => [
                '365',
                true
            ],
            'string 2556' => [
                '2556', // 7 years (days)
                true
            ],
            'empty string' => [
                '',
                false
            ],
            'int zero' => [
                0,
                false
            ],
            'int negative' => [
                -1,
                false
            ],
            'string zero' => [
                '0',
                false
            ],
            'string negative' => [
                '-5',
                false
            ],
            'string decimal' => [
                '1.5',
                false
            ],
            'string non-numeric' => [
                'foo',
                false
            ],
        ];
    }
}
