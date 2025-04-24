<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

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
        ];


        foreach ($tables as $table => $columns) {
            $start = microtime(true);
            $comments = $this->getDatabaseInternal()->executeQuery(
                "SELECT
                    `" . $columns['id'] . "` as id,
                    `" . $columns['comment'] . "` as comment
                FROM `$table`
                WHERE `" . $columns['comment'] . "` IS NOT NULL AND TRIM(`" . $columns['comment'] . "`) != ''"
            );

            if ($comments->rowCount() === 0) {
                $this->getLogger()->info("No comments to process in $table");
                continue;
            }

            $updateStatement = $this->getDatabaseInternal()->prepareQuery(
                "UPDATE `$table` SET `" . $columns['comment'] . "` = :comment WHERE `" . $columns['id'] . "` = :id"
            );

            foreach ($comments as $comment) {
                /**
                 * Start by converting any html entities to their unicode equivalents,
                 * as we still want "Jana, make sure you place a <p> tag around the text" to show up correctly
                 * and not be rendered as an actual <p> tag
                 * Also convert any \n to <br> tags, as we want to keep the line breaks
                 */
                $newComment = nl2br(Scrub::text($comment['comment']));

                /**
                 * Then, surround it in an actual <p> tag so that it can be rendered as if ckeditor made it
                 */
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

            $elapsed = microtime(true) - $start;
            $this->getLogger()->info(
                sprintf(
                    "Processed %d comments in %.2f s (%.4f ms/row) for %s",
                    $comments->rowCount(),
                    $elapsed,
                    $elapsed / $comments->rowCount() * 1000,
                    $table
                )
            );
        }
    }
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
