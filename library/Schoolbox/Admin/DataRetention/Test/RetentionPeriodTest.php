<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class RetentionPeriodTest extends TestCase
{
    public function testDefaultConstructionRetainsIndefinitely(): void
    {
        $rp = new RetentionPeriod();

        $this->assertSame(0, $rp->getRetentionDays());
        $this->assertSame('', $rp->getRetentionDatabase());
    }

    public function testSetWithIntegerDaysProducesDatabaseDate(): void
    {
        $days = 10;
        $rp = new RetentionPeriod($days);

        $this->assertSame($days, $rp->getRetentionDays());

        $expected = new \DateTime()->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $this->assertSame($expected, $rp->getRetentionDatabase());
    }

    public function testSetWithNumericStringIsAccepted(): void
    {
        $rp = new RetentionPeriod('5');

        $this->assertSame(5, $rp->getRetentionDays());

        $expected = new \DateTime()->modify('-5 days')->format('Y-m-d H:i:s');
        $this->assertSame($expected, $rp->getRetentionDatabase());
    }

    public function testNullOrEmptyStringDisablesRetention(): void
    {
        $rp = new RetentionPeriod(null);
        $this->assertSame(0, $rp->getRetentionDays());
        $this->assertSame('', $rp->getRetentionDatabase());

        $rp->setRetentionPeriod('');
        $this->assertSame(0, $rp->getRetentionDays());
        $this->assertSame('', $rp->getRetentionDatabase());
    }

    public function testInvalidNumericStringThrows(): void
    {
        $this->expectException(SchoolboxException::class);
        new RetentionPeriod('12ab');
    }

    public function testZeroDaysThrows(): void
    {
        $this->expectException(SchoolboxException::class);
        new RetentionPeriod(0);
    }

    public function testNegativeDaysThrows(): void
    {
        $this->expectException(SchoolboxException::class);
        new RetentionPeriod(-1);
    }

    public function testDecimalStringThrows(): void
    {
        $this->expectException(SchoolboxException::class);
        // Decimal strings should not be accepted (ctype_digit prevents this)
        new RetentionPeriod('1.5');
    }
}
