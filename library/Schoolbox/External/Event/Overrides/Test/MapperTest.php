<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides\Test;

use Schoolbox\Config\ArrayLoader;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Event\Overrides\Mapper;
use Schoolbox\External\Event\Overrides\Model;

/**
 * Tests for event override mapper hydration.
 */
class MapperTest extends TestCase
{
    public function testHydrateSelectKeepsClearStaffOverrideWithoutUser(): void
    {
        $mapper = new Mapper();
        $mapper->setConfigLoader(new ArrayLoader());
        $statement = $this->createStatement([
            'id' => 123,
            'event_id' => 456,
            'external_id' => null,
            'action' => Model::CLEAR_STAFF,
            'sequence' => 1,
            'created_at' => '2026-05-27 09:00:00',
            'updated_at' => '2026-05-27 09:00:00',
            'r.id' => null,
        ]);

        $method = new \ReflectionMethod($mapper, 'hydrateSelect');
        $overrides = $method->invoke($mapper, $statement);

        self::assertCount(1, $overrides);

        $override = $overrides[0];
        self::assertInstanceOf(Model::class, $override);
        self::assertSame(456, $override->getEventId());
        self::assertNull($override->getExternalId());
        self::assertSame(Model::CLEAR_STAFF, $override->getAction());
        self::assertSame(1, $override->getSequence());
    }

    private function createStatement(array $row): \PDOStatement
    {
        return new class ($row) extends \PDOStatement {
            /**
             * @param array<string, mixed> $row
             */
            public function __construct(private array $row)
            {
            }

            public function getIterator(): \Iterator
            {
                return new \ArrayIterator([$this->row]);
            }
        };
    }
}
