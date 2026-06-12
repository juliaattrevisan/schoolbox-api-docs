<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides\Test;

use PDO;
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
        $pdo = new PDO('sqlite::memory:');
        $statement = $pdo->prepare(
            'SELECT
                :id AS id,
                :event_id AS event_id,
                :external_id AS external_id,
                :action AS action,
                :sequence AS sequence,
                :created_at AS created_at,
                :updated_at AS updated_at,
                :role_id AS "r.id"'
        );

        $statement->execute([
            'id' => $row['id'],
            'event_id' => $row['event_id'],
            'external_id' => $row['external_id'],
            'action' => $row['action'],
            'sequence' => $row['sequence'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'role_id' => $row['r.id'],
        ]);

        return $statement;
    }
}
