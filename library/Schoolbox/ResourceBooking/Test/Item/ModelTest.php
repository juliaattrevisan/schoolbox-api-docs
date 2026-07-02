<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Test\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Item\Model;

#[CoversClass(Model::class)]
class ModelTest extends TestCase
{
    /**
     * @return array<string, array{capacity: int, quantity: int, expected: bool}>
     */
    public static function displayableCapacityProvider(): array
    {
        return [
            'capacity 1 quantity 1 hides quantity' => [
                'capacity' => 1,
                'quantity' => 1,
                'expected' => false,
            ],
            'capacity greater than 1 shows quantity 1' => [
                'capacity' => 2,
                'quantity' => 1,
                'expected' => true,
            ],
            'capacity 1 shows overbooked quantity' => [
                'capacity' => 1,
                'quantity' => 2,
                'expected' => true,
            ],
        ];
    }

    #[DataProvider('displayableCapacityProvider')]
    public function testHasDisplayableCapacityAppliesCapacityAndBookingQuantityRule(
        int $capacity,
        int $quantity,
        bool $expected
    ): void {
        $item = (new Model())->setCapacity($capacity);

        $this->assertSame($expected, $item->hasDisplayableCapacity($quantity));
    }

    public function testGetCapacityDisplayTextHidesCapacityOne(): void
    {
        $item = (new Model())->setCapacity(1);

        $this->assertNull($item->getCapacityDisplayText());
    }

    public function testGetCapacityDisplayTextShowsPluralCapacity(): void
    {
        $item = (new Model())->setCapacity(2);

        $this->assertSame('Capacity: 2 concurrent bookings', $item->getCapacityDisplayText());
    }

    public function testGetNameForBookingDisplayHidesDefaultSingleQuantity(): void
    {
        $item = (new Model())->setName('Camera')->setCapacity(1);

        $this->assertSame('Camera', $item->getNameForBookingDisplay(1));
    }

    public function testGetNameForBookingDisplayShowsReducedCapacityOverbookingQuantity(): void
    {
        $item = (new Model())->setName('Camera')->setCapacity(1);

        $this->assertSame('Camera (2)', $item->getNameForBookingDisplay(2));
    }

    public function testJsonSerializeIncludesAvailabilityResourceDetails(): void
    {
        $resource = (new Model())
            ->setCapacity(3)
            ->setAllowClashBookings(true);

        $serializedResource = $resource->jsonSerialize();

        $this->assertSame(3, $serializedResource['capacity']);
        $this->assertTrue($serializedResource['allowClashBookings']);
    }
}
