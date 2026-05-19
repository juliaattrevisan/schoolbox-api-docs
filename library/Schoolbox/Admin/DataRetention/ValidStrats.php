<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

/**
 * Decide which strategy names from a csv string are valid for the data retention CLI.
 *
 * This is a thin, testable helper around simple csv parsing and whitelisting:
 *  - Only names present in the provided valid strategy list are returned.
 *  - Comparison is currently case-sensitive to preserve existing behaviour.
 *  - Duplicates are removed.
 */
class ValidStrats
{
    // Strategy for hard-deleting learning moments and their associated data.
    public const string MOMENTS = 'moments';

    // Strategy for hard-deleting files uploaded by users in the My Files area.
    public const string MYFILES = 'myFiles';

    // Student submission cleanup must be explicitly selectable because it removes assessment evidence.
    public const string STUDENTSUBMISSIONS = 'studentSubmissions';

    // Strategy for hard-deleting data in the ePortfolio area.
    public const string EPORTFOLIO = 'ePortfolio';

    /**
     * These names are intentionally whitelisted so typos or future strategies
     * cannot silently change what the retention CLI is allowed to run.
     *
     * @var string[]
     */
    public const array STRATS_WHITELIST = [
        self::MOMENTS,
        self::MYFILES,
        self::STUDENTSUBMISSIONS,
        self::EPORTFOLIO,
    ];

    /**
     * After processing $csvArgs we remember what was valid
     * because we'll probably reuse this a few times and
     * we can save some cycles
     *
     * @var string[]
     */
    private array $filteredStrats;

    /**
     * @param string $csvArgs
     * @return void
     */
    public function __construct(string $csvArgs)
    {
        $this->filteredStrats = $this->filter($csvArgs);
    }

    /**
     * Filter a csv of requested strategies against the known valid list.
     *
     * Examples (assuming ['moments', 'myFiles'] as valid strategies):
     *  - "moments,myFiles" => ['moments', 'myFiles']
     *  - "moments,unknown" => ['moments']
     *  - "" or only commas/whitespace => []
     *
     * @param string $csvStrats raw csv string from CLI option
     * @return string[] filtered, canonical strategy names
     */
    public function filter(string $csvStrats): array
    {
        // Split csv args into individual pieces
        $parts = explode(',', $csvStrats);

        // Cast all elements to strings
        $parts = array_map('strval', $parts);

        // Trim all the strings in the set
        $parts = array_map('trim', $parts);

        // Remove anything that doesn't also appear in $validStrats and ignore those
        $filtered = array_intersect(self::STRATS_WHITELIST, $parts);

        // Return only the valid strats chosen
        return array_values($filtered);
    }

    /**
     * Return the validated, de-duplicated strategy names parsed from the constructor CSV input.
     *
     * @return string[]
     */
    public function getFilteredStrats(): array
    {
        return $this->filteredStrats;
    }

    /**
     * A convenience method that checks if a particular strat is in the result set
     *
     * @param string $strat
     * @return bool
     */
    public function isStratEnabled(string $strat): bool
    {
        if (!\count($this->filteredStrats)) {
            return true;
        }

        return \in_array($strat, $this->filteredStrats, true);
    }
}
