<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Quiz\Mapper;
use Schoolbox\Url\Factory;

/**
 * Learnosity Repair Quiz Tool
 *
 * Searches and will (if requested) re import the quiz
 */
class RepairBrokenTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';

    /**
     * --assessment
     *
     * update and fix the specified assessment
     */
    private const OPT_LONG_ASSESSMENT = 'assessment:';

    private API $api;

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT
        ];
    }

    /**
     * actually hit learnosity and confirm if they have any items, or not...
     * @param string $learnosityReference
     * @return array|null
     */
    private function hasItems(string $learnosityReference): ?array
    {
        $this->getLogger()->debug(sprintf('Polling learnosity to check if it has items: %s', $learnosityReference));
        $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
            'references' => [
                $learnosityReference
            ]
        ]);
        return $itemReferences['data'][0]['data']['items'] ?? null;
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Confirm with learnosity if the specified assessment contains any items, or searches all assessments for those with missing items.
If specified with [TODO:] will purge the learnosity reference for quizzes with no learnosity items, and reimports. if it still has no learnosity items, displays error.

Usage: repairBroken.php --assessment=... -p [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Repair Quiz tool.');
        $this->getLogger()->info('');

        /* Build dependencies
         */
        $this->api = new Api(
            (new Factory($this->getConfig()))->buildFullyQualified()->getHost(),
            $this->getConfig()->get('learnosity_version'),
            $this->getConfig()->get('learnosity_consumer_key'),
            $this->getConfig()->get('learnosity_consumer_secret'),
            $this->getLogger(),
        );

        // if assessment (no search)
        $assessmentId = trim((string)$this->getOpt(self::OPT_LONG_ASSESSMENT));

        if ('' !== $assessmentId) {
            $query = $this->getDatabaseInternal()->executeQuery("SELECT title, learnosity_reference from assess where assessment_id = ? AND availability = 'assess' and learnosity_reference is not NULL LIMIT 1", [$assessmentId]);
            // if no results... was this a quiz?
            if (!$query->rowCount()) {
                $this->getLogger()->error("Not a Quiz, please confirm the Assessment ID");
                exit;
            }
            $row = $query->fetch();
            if ($this->hasItems($row['learnosity_reference']) !== null) {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) successfully has learnosity items", $row['title'], $assessmentId));
            } else {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $assessmentId, $row['learnosity_reference']));
            }
        } else {
            $brokenQuizze = 0;
            // check all learnosity references
            $query = $this->getDatabaseInternal()->executeQuery("SELECT assessment_id, title, learnosity_reference from assess where availability = 'assess' and learnosity_reference is not NULL");
            $quizCount = $query->rowCount();
            $this->getLogger()->debug(sprintf("Found: %s quizzes, checking...", $quizCount));
            while ($row = $query->fetch()) {
                $this->getLogger()->debug(sprintf("Checking Quiz '%s' (Assessment Id: %s)", $row['title'], $row['assessment_id']));
                if (!$this->hasItems($row['learnosity_reference'])) {
                    $brokenQuizze++;
                    $this->getLogger()->debug(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $row['assessment_id'], $row['learnosity_reference']));
                }
            }
            $this->getLogger()->notice(
                sprintf("%s quizzes scanned, %s quizzes broken", $quizCount, $brokenQuizze)
            );
        }

        // fetch learnosity reference
        // check if missing items?
        // if perform, clear and re import
    }
}
