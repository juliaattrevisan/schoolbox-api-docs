<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\ValidRetentionPeriod;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class ValidRetentionPeriodTest extends TestCase
{
    /**
     * @param int|string $input
     */
    #[DataProvider('providerValidRetentionPeriods')]
    public function testAcceptsPositiveIntegersAndNumericStrings(int|string $input): void
    {
        $spec = new ValidRetentionPeriod();

        $this->assertTrue($spec->isSatisfiedBy($input));
    }

    public function testEmptyStringIsRejected(): void
    {
        $spec = new ValidRetentionPeriod();

        $this->assertFalse($spec->isSatisfiedBy(''));
    }

    /**
     * @param mixed $input
     */
    #[DataProvider('providerInvalidRetentionPeriods')]
    public function testInvalidValuesRaiseException(mixed $input): void
    {
        $this->expectException(SchoolboxException::class);

        $spec = new ValidRetentionPeriod();

        $spec->isSatisfiedBy($input);
    }

    public static function providerValidRetentionPeriods(): array
    {
        return [
            'int 1' => [1],
            'int 30' => [30],
            'string 1' => ['1'],
            'string 365' => ['365'],
        ];
    }

    public static function providerInvalidRetentionPeriods(): array
    {
        return [
            'int zero' => [0],
            'int negative' => [-1],
            'string zero' => ['0'],
            'string negative' => ['-5'],
            'string decimal' => ['1.5'],
            'string non-numeric' => ['foo'],
        ];
    }
}
