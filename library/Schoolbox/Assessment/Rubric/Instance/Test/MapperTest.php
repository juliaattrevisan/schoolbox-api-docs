<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Instance\Test;

use Alaress\Database\Statement\Query\IQuery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Instance\Mapper;
use Schoolbox\Test\MockConnector;

#[CoversClass(Mapper::class)]
#[AllowMockObjectsWithoutExpectations]
class MapperTest extends TestCase
{
    /**
     * Tests that an empty preservation list soft-deletes every active rubric instance for an assessment.
     *
     * @return void
     */
    public function testRemoveFromAssessmentWithNoPreservedInstancesDeletesAllActiveInstances(): void
    {
        $database = new class ($this, $this->createStub(IQuery::class)) extends MockConnector {
            public string $query = '';
            public ?array $parameters = null;

            public function __construct(TestCase $testCase, private readonly IQuery $queryResult)
            {
                parent::__construct($testCase);
            }

            public function executeQuery($sql, $parameters = null): IQuery
            {
                $this->query = $sql;
                $this->parameters = $parameters;

                return $this->queryResult;
            }
        };

        (new Mapper())
            ->setDatabase($database)
            ->removeFromAssessmentId(123, []);

        $query = preg_replace('/\s+/', ' ', trim($database->query));

        self::assertSame(
            'UPDATE rubric_instance SET deleted_at = NOW() '
                . 'WHERE assessment_id = :assessmentId AND deleted_at IS NULL',
            $query,
        );
        self::assertSame(['assessmentId' => 123], $database->parameters);
    }
}
