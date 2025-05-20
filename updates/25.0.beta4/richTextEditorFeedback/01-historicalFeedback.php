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
            'lti_assessment_score' => [
                'id' => 'id',
                'comment' => 'comment',
            ], // i.e. lti
        ];


        foreach ($tables as $table => $columns) {
            $start = microtime(true);
            $comments = $this->getDatabaseInternal()->executeQuery(
                "SELECT
                    `" . $columns['id'] . "` as id,
                    `_" . $columns['comment'] . "` as comment
                FROM `$table`
                WHERE
                    `_" . $columns['comment'] . "` IS NOT NULL AND
                    `_" . $columns['comment'] . "` != ''"
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
                 * Scrub::linkifyText replaces any links and some markdown with html, like it used to in some areas
                 */
                $newComment = Scrub::linkifyText(Scrub::text($comment['comment']));

                /** For some reason linkifyText adds a new line at the end, so remove that
                 *  Then convert any remaining \n to <br> tags, as we want to keep the line breaks
                 *  and finally, surround it in an actual <p> tag so that it can be rendered as if ckeditor made it
                 */
                $newComment = rtrim($newComment, "\n");
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

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
