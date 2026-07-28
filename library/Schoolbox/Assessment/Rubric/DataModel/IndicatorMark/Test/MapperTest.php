<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Test;

use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
use Schoolbox\Test\MockConnector;

#[\PHPUnit\Framework\Attributes\CoversClass(Mapper::class)]
#[AllowMockObjectsWithoutExpectations]
class MapperTest extends TestCase
{
    /**
     * Tests that assessment rubric mark lookups exclude soft-deleted rubric instances.
     *
     * @param class-string<IAssessable> $assessmentClass     The assessment class to test.
     * @param string                    $assessmentReference The assessment ID column used by the query.
     * @return void
     */
    #[DataProvider('assessmentProvider')]
    public function testFindMarkedByAssessmentIgnoresMarksForDeletedRubricInstances(
        string $assessmentClass,
        string $assessmentReference,
    ): void {
        $database = new class ($this) extends MockConnector {
            public string $query = '';

            public function prepare(string $statement, array $driverOptions = []): PDOStatement
            {
                $this->query = $statement;

                return parent::prepare($statement, $driverOptions);
            }
        };
        $database->setNextFetchAll([['student_id' => 42]]);

        $assessment = (new $assessmentClass())->setId(123);
        $marked = (new Mapper())
            ->setDatabase($database)
            ->findMarkedByAssessment($assessment);

        $query = preg_replace('/\s+/', ' ', trim($database->query));

        self::assertSame([42 => true], $marked);
        self::assertStringContainsString(
            "INNER JOIN rubric_instance ri ON rcm.rubric_instance_id = ri.id "
                . "AND ri.assessment_id = {$assessmentReference} AND ri.deleted_at IS NULL",
            $query,
        );
    }

    /**
     * Provides supported assessment classes and their corresponding SQL assessment references.
     *
     * @return array<string, array{class-string<IAssessable>, string}>
     */
    public static function assessmentProvider(): array
    {
        return [
            'Due Work' => [DueWork::class, 'sb.assessment_id'],
            'Reporting' => [Reporting::class, 'r.assessment_id'],
        ];
    }
}
