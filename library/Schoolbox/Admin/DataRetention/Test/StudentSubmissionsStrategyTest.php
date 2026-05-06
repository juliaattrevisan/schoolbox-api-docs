<?php

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention\Test;

use Alaress\Database\Connector\IConnector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\RetentionPeriod;
use Schoolbox\Admin\DataRetention\Strategy\StudentSubmissions;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class StudentSubmissionsStrategyTest extends TestCase
{
    /**
     * Builds a test-specific strategy subclass so these tests can verify the
     * orchestration and delete ordering without coupling to a live database.
     *
     * @param array<int, list<int>> $submissionFiles
     * @return array{
     *     strategy: StudentSubmissions,
     *     state: object{
     *         calls: list<array{0: string, 1: list<int>}>,
     *         deletedSubmissions: list<int>,
     *         deletedSubmissionFiles: list<int>
     *     }
     * }
     */
    private function strategy(array $submissionFiles): array
    {
        $logger = $this->createStub(LoggerInterface::class);
        $database = $this->createStub(IConnector::class);
        $lock = $this->createStub(LockInterface::class);
        $config = $this->createStub(IConfigLoader::class);

        $logger->method('debug');
        $logger->method('notice');
        $logger->method('info');

        $state = new class {
            /** @var list<array{0: string, 1: list<int>}> */
            public array $calls = [];

            /** @var list<int> */
            public array $deletedSubmissions = [];

            /** @var list<int> */
            public array $deletedSubmissionFiles = [];
        };

        $strategy = new class (
            $logger,
            true,
            $database,
            $lock,
            $config,
            time() + 3600,
            new RetentionPeriod(30),
            $submissionFiles,
            $state,
        ) extends StudentSubmissions {
            /** @var array<int, list<int>> */
            private array $submissionFiles;

            private object $state;

            /**
             * Accepts the fake submission-file graph and shared state recorder
             * so each test can express only the retention behaviour it cares
             * about, instead of setting up PDO expectations.
             *
             * @param LoggerInterface $logger
             * @param bool $perform
             * @param IConnector $database
             * @param LockInterface $lock
             * @param IConfigLoader $config
             * @param int $stoppingTime
             * @param RetentionPeriod $retentionPeriod
             * @param array<int, list<int>> $submissionFiles
             * @param object $state
             * @return void
             */
            public function __construct(
                LoggerInterface $logger,
                bool $perform,
                IConnector $database,
                LockInterface $lock,
                IConfigLoader $config,
                int $stoppingTime,
                RetentionPeriod $retentionPeriod,
                array $submissionFiles,
                object $state,
            ) {
                parent::__construct($logger, $perform, $database, $lock, $config, $stoppingTime, $retentionPeriod);
                $this->submissionFiles = $submissionFiles;
                $this->state = $state;
            }

            /**
             * Replaces the SQL lookup with an in-memory relation map so the
             * tests prove the strategy normalizes and orders resolved file ids.
             *
             * @param array<int, int> $submissionIds
             * @return array<int, int>
             */
            protected function findSubmissionFileIds(array $submissionIds): array
            {
                $fileIds = [];
                foreach ($submissionIds as $submissionId) {
                    foreach ($this->submissionFiles[$submissionId] ?? [] as $fileId) {
                        $fileIds[] = $fileId;
                    }
                }

                return $fileIds;
            }

            /**
             * Records the student submission file delete call after all
             * integration rows have been cleared.
             *
             * @param array<int, int> $fileIds
             * @return int
             */
            protected function deleteSubmissionFiles(array $fileIds): int
            {
                $this->state->calls[] = ['student_submission_file', $fileIds];
                $this->state->deletedSubmissionFiles = $fileIds;

                return count($fileIds);
            }

            /**
             * Records integration-table deletes so tests can verify they occur
             * before submission files without requiring a PDO test double.
             *
             * @param string $table
             * @param array<int, int> $fileIds
             * @return int
             */
            protected function deleteRowsByStudentSubmissionFileId(string $table, array $fileIds): int
            {
                $this->state->calls[] = [$table, $fileIds];

                return count($fileIds);
            }

            /**
             * Records the final parent delete call so tests can assert that
             * submissions with no files are still purged.
             *
             * @param array<int, int> $submissionIds
             * @return int
             */
            protected function deleteSubmissions(array $submissionIds): int
            {
                $this->state->calls[] = ['student_submission', $submissionIds];
                $this->state->deletedSubmissions = $submissionIds;

                return count($submissionIds);
            }
        };

        return [
            'strategy' => $strategy,
            'state' => $state,
        ];
    }

    /**
     * Invokes the protected chunk method directly because the strategy's
     * transaction loop is already covered by Base and these tests focus on the
     * dependency chain inside one chunk.
     *
     * @param StudentSubmissions $strategy
     * @param array<int, array{id: int|string}> $chunk
     * @return void
     */
    private function delete(StudentSubmissions $strategy, array $chunk): void
    {
        $delete = \Closure::bind(
            function (array $chunk): void {
                $this->deleteChunk($chunk);
            },
            $strategy,
            StudentSubmissions::class,
        );

        $delete($chunk);
    }

    /**
     * The Jira-listed child tables must be deleted before submission files, and
     * files before submissions, so the chunk is safe under foreign-key checks.
     *
     * @return void
     */
    public function testDeletesChildrenBeforeParents(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([
            10 => [101, 102],
            11 => [103],
        ]);

        $this->delete($strategy, [
            ['id' => 10],
            ['id' => 11],
        ]);

        $this->assertSame([
            ['turnitin_report', [101, 102, 103]],
            ['plagscan_document', [101, 102, 103]],
            ['kami_document', [101, 102, 103]],
            ['student_submission_file', [101, 102, 103]],
            ['student_submission', [10, 11]],
        ], $state->calls);
    }

    /**
     * A soft-deleted submission should still be hard-deleted even when it has
     * no attached files, otherwise orphan-free submissions would be retained.
     *
     * @return void
     */
    public function testDeletesSubmissionWithoutFiles(): void
    {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy([
            10 => [],
        ]);

        $this->delete($strategy, [
            ['id' => 10],
        ]);

        $this->assertSame([
            ['student_submission', [10]],
        ], $state->calls);
        $this->assertSame([10], $state->deletedSubmissions);
        $this->assertSame([], $state->deletedSubmissionFiles);
    }

    /**
     * Delete chunks can contain repeated ids from batching or future query
     * changes, so the strategy should produce deterministic unique delete sets.
     *
     * @param array<int, array{id: int|string}> $chunk
     * @param array<int, list<int>> $submissionFiles
     * @param list<int> $expectedSubmissionIds
     * @param list<int> $expectedFileIds
     * @return void
     */
    #[DataProvider('providerNormalizesDeleteSets')]
    public function testNormalizesDeletes(
        array $chunk,
        array $submissionFiles,
        array $expectedSubmissionIds,
        array $expectedFileIds,
    ): void {
        ['strategy' => $strategy, 'state' => $state] = $this->strategy($submissionFiles);

        $this->delete($strategy, $chunk);

        $this->assertSame($expectedSubmissionIds, $state->deletedSubmissions);
        $this->assertSame($expectedFileIds, $state->deletedSubmissionFiles);
    }

    /**
     * Supplies mixed string/integer ids because PDO fetches can vary by driver
     * and normalization should not depend on that detail.
     *
     * @return array<string, array{
     *     0: array<int, array{id: int|string}>,
     *     1: array<int, list<int>>,
     *     2: list<int>,
     *     3: list<int>
     * }>
     */
    public static function providerNormalizesDeleteSets(): array
    {
        return [
            'duplicate and mixed submission ids' => [
                [
                    ['id' => '20'],
                    ['id' => 10],
                    ['id' => 20],
                    ['id' => '10'],
                ],
                [
                    10 => [102, 101, 101],
                    20 => [202],
                ],
                [10, 20],
                [101, 102, 202],
            ],
            'invalid ids are ignored' => [
                [
                    ['id' => 0],
                    ['id' => '-1'],
                    ['id' => 30],
                ],
                [
                    30 => [302, 301],
                ],
                [30],
                [301, 302],
            ],
        ];
    }
}
