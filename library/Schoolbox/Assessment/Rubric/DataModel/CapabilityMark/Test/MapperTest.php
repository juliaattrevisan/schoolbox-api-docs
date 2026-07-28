<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Test;

use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Mapper;
use Schoolbox\Test\MockConnector;

#[CoversClass(Mapper::class)]
#[AllowMockObjectsWithoutExpectations]
class MapperTest extends TestCase
{
    /**
     * Tests that all marks linked to a rubric instance are invalidated without relying on a feedback type.
     *
     * @return void
     */
    public function testInvalidateForRubricInstanceInvalidatesCapabilityAndIndicatorMarks(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement
            ->expects($this->once())
            ->method('execute')
            ->with(['rubricInstanceId' => 123])
            ->willReturn(true);

        $database = new class ($this, $statement) extends MockConnector {
            public string $query = '';

            public function __construct(
                TestCase $testCase,
                private readonly PDOStatement $statement,
            ) {
                parent::__construct($testCase);
            }

            public function prepare(string $statement, array $driverOptions = []): PDOStatement
            {
                $this->query = $statement;

                return $this->statement;
            }
        };

        new Mapper()
            ->setDatabase($database)
            ->invalidateForRubricInstance(123);

        $query = preg_replace('/\s+/', ' ', trim($database->query));

        self::assertSame(
            'UPDATE rubric_capability_mark rcm '
                . 'LEFT JOIN rubric_indicator_mark rim ON rim.rubric_capability_mark_id = rcm.id '
                . 'SET rcm.valid = FALSE, rim.valid = FALSE '
                . 'WHERE rcm.rubric_instance_id = :rubricInstanceId',
            $query,
        );
    }
}
