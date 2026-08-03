<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Test;

use Alaress\Database\Statement\Query\IQuery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Instance\Mapper as RubricInstanceMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper;
use Schoolbox\Learning\Assessment\DataModel\Model;
use Schoolbox\Test\MockConnector;

#[CoversClass(Mapper::class)]
#[AllowMockObjectsWithoutExpectations]
class AssessmentRubricDeletionTest extends TestCase
{
    public function testSoftDeleteRubricInstancesDeletesEveryInstanceForAssessment(): void
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

        $rubricInstanceMapper = RubricInstanceMapper::getInstance();
        $originalDatabase = $rubricInstanceMapper->getDatabase();
        $rubricInstanceMapper->setDatabase($database);

        try {
            (new Mapper())->softDeleteRubricInstances((new Model())->setId(123));
        } finally {
            $rubricInstanceMapper->setDatabase($originalDatabase);
        }

        $query = preg_replace('/\s+/', ' ', trim($database->query));

        self::assertSame(
            'UPDATE rubric_instance SET deleted_at = NOW() '
                . 'WHERE assessment_id = :assessmentId AND deleted_at IS NULL',
            $query,
        );
        self::assertSame(['assessmentId' => 123], $database->parameters);
    }
}
