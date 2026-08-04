<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Test;

use Alaress\Database\Statement\Query\IQuery;
use Closure;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Schoolbox\Assessment\Rubric\Instance\Mapper as RubricInstanceMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\Model;
use Schoolbox\Test\MockConnector;

#[CoversClass(AssessmentMapper::class)]
#[AllowMockObjectsWithoutExpectations]
class AssessmentRubricDeletionTest extends TestCase
{
    /**
     * Tests that deleting an assessment soft-deletes all of its rubric instances.
     *
     * @return void
     */
    public function testRegisteredRubricCallbackDeletesEveryInstanceForAssessment(): void
    {
        // Extend the AssessmentMapper so the findPostSoftDeleteCallback() method will have access to its callbacks.
        $assessmentMapper = new class extends AssessmentMapper {
            /**
             * Finds a post-soft-delete callback by its method name.
             *
             * @param string $methodName
             * @return Closure|null
             */
            public function findPostSoftDeleteCallback(string $methodName): ?Closure
            {
                $callbacks = $this->callback_queue[self::CALLBACK_POSTSOFTDELETE] ?? [];

                foreach ($callbacks as $callback) {
                    $closure = $callback(...);

                    if (new ReflectionFunction($closure)->getName() === $methodName) {
                        return $closure;
                    }
                }

                return null;
            }
        };

        $callback = $assessmentMapper->findPostSoftDeleteCallback('softDeleteRubricInstances');

        self::assertNotNull(
            $callback,
            'The rubric cleanup must be registered as a post-soft-delete callback.',
        );

        $database = new class ($this, $this->createStub(IQuery::class)) extends MockConnector {
            public string $query = '';
            public ?array $parameters = null;

            /**
             * Builds a connector that records the query executed by the mapper.
             *
             * @param TestCase $testCase
             * @param IQuery $queryResult
             */
            public function __construct(TestCase $testCase, private readonly IQuery $queryResult)
            {
                parent::__construct($testCase);
            }

            /**
             * Records a query and returns the configured query result.
             *
             * @param string $sql
             * @param array|null $parameters
             * @return IQuery
             */
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
            $callback(new Model()->setId(123));
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
