<?php

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Version\Version;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Migrates all old feedback comments to be rich text
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $tables = [
            'submission_return' => [
                'id' => 'id',
                'comment' => 'comment',
            ], // i.e. due work
            'assess_attempt' => [
                'id' => 'attempt_id',
                'comment' => 'comment',
            ], // i.e. quiz/survey
            'lti_assessment_score' => [
                'id' => 'id',
                'comment' => 'comment',
            ], // i.e. lti
        ];

        // Arbitrary chunk size:
        // only fetch this number of comment rows per loop
        $chunkSize = 1000;

        // Sleep for up to this long (microseconds) between iterations,
        // just so that the update is staggered a little on cloud.
        $maxSleepMicroseconds = 250_000;

        // Where is this being run?
        // If it's on cloud, add some backoff between chunks so that not all
        // updates are hitting the DB at once.
        $instanceInfo = new Version($this->getConfig());
        $isCloud      = $instanceInfo->isCloudHosted();

        $remainingCounts = [];
        foreach ($tables as $table => $columns) {
            $start = microtime(true);
            $remainingCounts[$table] = 0;

            // First output a count (can remove if too slow)
            $commentCountQuery = $this->getDatabaseInternal()->executeQuery("
                SELECT COUNT(*)
                FROM `$table`
                WHERE `_{$columns['comment']}` IS NOT NULL
                AND   `_{$columns['comment']}` != ''
                AND   `{$columns['comment']}`  IS NULL
            ");
            $commentCount = $commentCountQuery->fetchColumn();
            if ($commentCount === 0) {
                $this->getLogger()->info("No comments to process in $table");
                continue;
            }
            $commentCountQuery->close();

            // Max iterations should be no more than $commentCount / $chunkSize
            $maxIterations = ceil($commentCount / $chunkSize);
            $this->getLogger()->info(
                sprintf(
                    "Found %d comments for %s (max iterations: %d)",
                    $commentCount,
                    $table,
                    $maxIterations,
                )
            );

            // Create a chunk fetch query:
            // only processes rows whose new comment isn't yet set
            $commentsStatement = $this->getDatabaseInternal()->executeQuery("
                SELECT
                    `{$columns['id']}` as id,
                    `_{$columns['comment']}` as comment
                FROM `$table`
                WHERE `_{$columns['comment']}` IS NOT NULL
                AND   `_{$columns['comment']}` != ''
                AND   `{$columns['comment']}`  IS NULL
                ORDER BY `{$columns['id']}` DESC
                LIMIT $chunkSize OFFSET 0
            ");

            // prepared statement for updating comments
            $updateStatement = $this->getDatabaseInternal()->prepareQuery("
                UPDATE `$table`
                SET `{$columns['comment']}` = :comment
                WHERE `{$columns['id']}` = :id
            ");

            // How many rows have been processed?
            $runningCount = 0;

            // Failsafe for infinite loop
            $iteration = 0;
            while ($iteration < $maxIterations) {
                $iteration++;
                $commentsStatement->execute();
                $commentChunkCount = $commentsStatement->rowCount();
                if ($commentChunkCount === 0) {
                    $this->getLogger()->info(sprintf('No more %s comments to process', $table));
                    break;
                }

                $runningCount += $commentChunkCount;
                $this->getLogger()->info(
                    sprintf(
                        'Processing %d %s comments (approx. %d remaining)',
                        $commentChunkCount,
                        $table,
                        $commentCount - $runningCount,
                    )
                );
                $comments = $commentsStatement->fetchAll();
                $commentsStatement->close();

                foreach ($comments as $comment) {
                    /**
                     * Start by converting any html entities to their unicode equivalents,
                     * as we still want "Jana, make sure you place a <p> tag around the text" to show up correctly
                     * and not be rendered as an actual <p> tag
                     * Scrub::linkifyText replaces any links and some markdown with html, like it used to in some areas
                     */
                    $newComment = Scrub::linkifyText(Scrub::text($comment['comment']));

                    /** For some reason linkifyText adds a new line at the end, so remove that
                     *  Then convert any remaining \n to <br> tags, as we want to keep the line breaks
                     *  and finally, surround it in an actual <p> tag so that it can be rendered as if ckeditor made it
                     */
                    $newComment = nl2br(rtrim($newComment, "\n"));
                    $newComment = "<p>$newComment</p>";

                    /**
                     * this is probably mighty unperformant... if it takes too long to run then we could
                     * consider doing only more recent comments, or do it in batches with a cron I guess
                     */
                    $updateStatement->execute([
                        "comment" => $newComment,
                        "id" => $comment["id"],
                    ]);
                }

                // Go to sleep for a little bit, on cloud only:
                // this is to reduce load on multi-instance cloud servers, which
                // may be updating multiple instances at once.
                if ($isCloud) {
                    $sleep = random_int(0, $maxSleepMicroseconds);
                    $this->getLogger()->info(sprintf("Waiting %0.2fs for next process", $sleep / 1_000_000));
                    usleep($sleep);
                }
            }

            $elapsed = microtime(true) - $start;
            $this->getLogger()->info(
                sprintf(
                    "Processed %d comments in %.2f s (%.4f ms/row) for %s",
                    $commentCount,
                    $elapsed,
                    $elapsed / $commentCount * 1000,
                    $table
                )
            );

            // Are there any remaining? Raise warning if so
            $commentCountQuery->execute();
            $commentCount = $commentCountQuery->fetchColumn();
            if ($commentCount > 0) {
                $this->getLogger()->warning(
                    sprintf("%d comments in %s have not been processed!", $commentCount, $table),
                );
            }
            $remainingCounts[$table] = $commentCount;
        }

        // Summarise, and error if not everything was processed
        $exitCode = CliKernelAbstract::EXIT_STATUS_OK;
        $this->getLogger()->info("Comments not processed:");
        foreach ($remainingCounts as $table => $count) {
            $this->getLogger()->info(
                sprintf("  %s: %d", $table, $commentCount),
            );
            if ($count > 0) {
                $exitCode = CliKernelAbstract::EXIT_STATUS_APP_ERROR;
            }
        }
        if ($exitCode === CliKernelAbstract::EXIT_STATUS_APP_ERROR) {
            $this->getLogger()->warning('Not all comments were processed successfully.');
        }
        exit($exitCode);
    }
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
