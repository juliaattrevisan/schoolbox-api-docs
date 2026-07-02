<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Test\Booking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Booking\Model;

#[CoversClass(Model::class)]
class ModelTest extends TestCase
{
    public function testQuantityDefaultsToOne(): void
    {
        $this->assertSame(1, (new Model())->getQuantity());
    }

    public function testQuantityCanBeChanged(): void
    {
        $booking = (new Model())->setQuantity(3);

        $this->assertSame(3, $booking->getQuantity());
    }
}
