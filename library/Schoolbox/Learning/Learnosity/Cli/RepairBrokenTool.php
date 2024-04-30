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

    /**
     * --repair
     *
     * repair the specified assessment
     */
    private const OPT_LONG_REPAIR = 'repair';

    private API $api;

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
If specified with --repair will purge the learnosity reference for quizzes with no learnosity items, and reimports. if it still has no learnosity items, displays error.

Usage: repairBroken.php --assessment=... -p --repair [-vvv]


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
            if ($this->hasItems($row['learnosity_reference']) !== null) {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) successfully has learnosity items", $row['title'], $assessmentId));
            } else {
                $this->getLogger()->notice(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $assessmentId, $row['learnosity_reference']));
                if (null === $row['assess_section.assess_id']) {
                    $this->getLogger()->notice("Quiz build in learnosity, unable to re import. Manual work is required... Good luck...");
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
                }
            }
        } else {
            $brokenQuizze = 0;
            // check all learnosity references
            $query = $this->getDatabaseInternal()->executeQuery(
                "SELECT assessment_id, assess.title, learnosity_reference, assess_section.assess_id as `assess_section.assess_id`
                        FROM assess
                        LEFT JOIN assess_section ON assess.assess_id = assess_section.assess_id
                        WHERE availability = 'assess' AND learnosity_reference IS NOT NULL"
            );
            $quizCount = $query->rowCount();
            $this->getLogger()->debug(sprintf("Found: %s quizzes, checking...", $quizCount));
            while ($row = $query->fetch()) {
                $this->getLogger()->debug(sprintf("Checking Quiz '%s' (Assessment Id: %s)", $row['title'], $row['assessment_id']));
                if (!$this->hasItems($row['learnosity_reference'])) {
                    $brokenQuizze++;
                    $this->getLogger()->debug(sprintf("Quiz '%s' (Assessment Id: %s) has no learnosity items (learnosity reference: '%s')", $row['title'], $row['assessment_id'], $row['learnosity_reference']));
                    if (null === $row['assess_section.assess_id']) {
                        $this->getLogger()->notice("Quiz build in learnosity, unable to re import. Manual work is required... Good luck...");
                    }
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
