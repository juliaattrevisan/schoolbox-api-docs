<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\Cli\ImporterTool;
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

    /**
     * --repair
     *
     * repair the specified assessment
     */
    private const OPT_LONG_REPAIR = 'repair';

    private API $api;

    /**
     * array to cache the learnosity activity payload data, which includes the quiz title, description and the items (questions)
     * @var array
     */
    private array $learnosityActivity = [];

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_ASSESSMENT,
            self::OPT_LONG_REPAIR,
        ];
    }

    /**
     * Hit Learnosity to get either a single or multiple activity data
     * @param array $learnosityReferences
     * @return void
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function fetchActivity(array $learnosityReferences): void
    {
        $this->getLogger()->debug(sprintf("%s activities to fetch, chunking into 999 sized chunks", count($learnosityReferences)));

        // Learnosity has a limit of 1000 activities on the endpoint, lets get close but not quite hit it...
        foreach (array_chunk($learnosityReferences, 999) as $chunk) {
            $this->getLogger()->debug(sprintf('Polling learnosity to check if it has items: %s', implode(',', $chunk)));
            $itemReferences = $this->api->dataGetRequest('/itembank/activities', [
                'references' => $chunk
            ]);
            foreach ($itemReferences['data'] as $item) {
                $this->learnosityActivity[$item['reference']] = $item;
            }
        }
    }

    /**
     * Check if the specified learnosity reference was built correctly...
     * Each activity should have a title that is not null, and have a list of items (questions/sections)
     * @param string $learnosityReference
     * @return bool
     */
    private function hasItems(string $learnosityReference, $cache = true): bool
    {
        // if we are just using the already hydrated cache (and the entry exists in there use it, else poll the server
        if (!$cache || !in_array($learnosityReference, $this->learnosityActivity)) {
            $this->fetchActivity([$learnosityReference]);
        }

        // now we have the 'new/cached' learnosity activity, lets check it
        $activity = $this->learnosityActivity[$learnosityReference];
        if (null === $activity) {
            $this->getLogger()->debug("learnosity activity has no data... probably good to reimport.");
            return false;
        }
        $title = $activity['title'] ?? null;
        $items = $activity['data']['items'] ?? null;
        if (null === $title) {
            $this->getLogger()->debug("learnosity activity has no title... probably safe to reimport.");
        }
        if (null !== $title && null === $items) {
            $this->getLogger()->debug("learnosity activity has title, but no items... school may have removed the items, manually?");
        }
        // we should ensure both the title and the items are null, not just empty
        if (null === $title && null === $items) {
            return false;
        }
        return true;
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Confirm with learnosity if the specified assessment contains any items, or searches all assessments for those with missing items.
If specified with --repair will purge the learnosity reference for quizzes with no learnosity items, and reimports. if it still has no learnosity items, displays error.

Usage: repairBroken.php --assessment=... -p --repair [-vvv]
To scan: repairBroken.php (will give summary at the end)
To Repair: repairBroken.php --assessment=... -p --repair (single assessment at a time)


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
            $query = $this->getDatabaseInternal()->executeQuery(
                "SELECT assess.title, learnosity_reference, assess_section.assess_id as `assess_section.assess_id`
                        FROM assess
                        LEFT JOIN assess_section ON assess.assess_id = assess_section.assess_id
                        WHERE assessment_id = :assessmentId AND availability = 'assess' AND learnosity_reference IS NOT NULL
                        LIMIT 1",
                ['assessmentId' => $assessmentId]
            );
            // if no results... was this a quiz?
            if (!$query->rowCount()) {
                $this->getLogger()->error("Not a Quiz, please confirm the Assessment ID");
                exit;
            }
            $row = $query->fetch();
            if ($this->hasItems($row['learnosity_reference'])) {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) successfully has learnosity items", $row['title'], $assessmentId));
            } else {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $assessmentId, $row['learnosity_reference']));
                if (null === $row['assess_section.assess_id']) {
                    $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) build in learnosity, unable to re import. Manual work is required... Good luck...", $row['title'], $assessmentId));
                    exit;
                }
                // fix this?
                if ($this->getOpt(self::OPT_LONG_REPAIR)) {
                    if ($this->perform()) {
                        $this->getLogger()->notice("Repairing...");
                        $this->getDatabaseInternal()->executeQuery("UPDATE assess SET learnosity_reference = null WHERE assessment_id = :assessmentId LIMIT 1", ['assessmentId' => $assessmentId]);
                    } else {
                        $this->getLogger()->notice("Planning to repair...");
                        $this->getLogger()->debug(sprintf("Would have cleared the learnosity reference for Quiz '%s' (Assessment Id: %s)...", $row['title'], $assessmentId));
                    }
                    $app = new ImporterTool();
                    $options = [CliTool\Kernel::OPTION_LEVEL => \Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

                    (new CliTool\Kernel($app, $options))->runApp();

                    // wait for learnosity to persist the data to where it needs to be
                    // just set a random number (15sec may be enough..)
                    sleep(15);
                    // check / ensure the quiz has been fixed...
                    $query = $this->getDatabaseInternal()->executeQuery(
                        "SELECT assess.title, learnosity_reference, assess_section.assess_id as `assess_section.assess_id`
                        FROM assess
                        LEFT JOIN assess_section ON assess.assess_id = assess_section.assess_id
                        WHERE assessment_id = :assessmentId AND availability = 'assess' AND learnosity_reference IS NOT NULL
                        LIMIT 1",
                        ['assessmentId' => $assessmentId]
                    );
                    // if no results... something broke...?
                    if (!$query->rowCount()) {
                        $this->getLogger()->error(sprintf("Quiz repair broke for %s, no learnosity reference...", $assessmentId));
                        exit;
                    }
                    $row = $query->fetch();
                    // force the cached entry to be re fetched (we should be generating a new uuid here..
                    // but just to ensure we are grabbing the latest learnosity entry, do it again
                    $this->fetchActivity([$row['learnosity_reference']]);

                    // do we now have items? did the reimport fix the issue?
                    if ($this->hasItems($row['learnosity_reference'])) {
                        $this->getLogger()->notice(sprintf("Quiz repair succeeded %s, has questions", $assessmentId));
                    } else {
                        $this->getLogger()->error(sprintf("Quiz repair broke for %s. Still no items...", $assessmentId));
                    }
                }
            }
        } else {
            if ($this->getOpt(self::OPT_LONG_REPAIR)) {
                $this->getLogger()->error("Unable to bulk repair, either scan for issues, or repair a single");
                exit;
            }
            $brokenQuizzes = 0;
            // check all learnosity references
            $query = $this->getDatabaseInternal()->executeQuery(
                "SELECT assessment_id, assess.title, learnosity_reference, assess_section.assess_id as `assess_section.assess_id`
                        FROM assess
                        LEFT JOIN assess_section ON assess.assess_id = assess_section.assess_id
                        WHERE availability = 'assess' AND learnosity_reference IS NOT NULL"
            );
            $quizCount = $query->rowCount();
            $this->getLogger()->debug(sprintf("Found: %s quizzes, checking...", $quizCount));

            // hit learnosity and fetch them all
            $this->fetchActivity($query->fetchColumnArray('learnosity_reference'));

            while ($row = $query->fetch()) {
                $this->getLogger()->debug(sprintf("Checking Quiz '%s' (Assessment Id: %s)", $row['title'], $row['assessment_id']));
                if (!$this->hasItems($row['learnosity_reference'])) {
                    $brokenQuizzes++;
                    $this->getLogger()->debug(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $row['assessment_id'], $row['learnosity_reference']));
                    if (null === $row['assess_section.assess_id']) {
                        $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) build in learnosity, unable to re import. Manual work is required... Good luck...", $row['title'], $row['assessment_id']));
                    }
                }
            }
            $this->getLogger()->notice(
                sprintf("%s quizzes scanned, %s quizzes broken", $quizCount, $brokenQuizzes)
            );
        }
    }
}
