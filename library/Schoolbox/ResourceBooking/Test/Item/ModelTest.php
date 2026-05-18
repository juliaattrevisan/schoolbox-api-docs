<?php

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Test\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\ResourceBooking\Item\Model;

#[CoversClass(Model::class)]
class ModelTest extends TestCase
{
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
