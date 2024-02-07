<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

class Aggregate implements IHydrator
{
    /**
     * @var IHydrator[]
     */
    private array $hydrators;

    /**
     * Constructor
     *
     * @param IHydrator ...$hydrators
     */
    public function __construct(IHydrator ...$hydrators)
    {
        $this->hydrators = $hydrators;
    }

    /**
     * True if any of this aggregate's hydrators accept this result.
     *
     * @param RawResult $match
     * @return bool
     */
    public function accepts(RawResult $match): bool
    {
        foreach ($this->hydrators as $hydrator) {
            if ($hydrator->accepts($match)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hydrate raw matches into a proper result, by attempting to hydrate the
     * list of matches with each hydrator in this aggregate.
     *
     * @param string                $keyword
     * @param array<int, RawResult> $matches A set of matches to hydrate.
     * @param User                  $viewer
     * @return array<int, FullResult|null>
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        if ($viewer->getId() === 1) {
            $t0 = microtime(true);
            echo "Aggregate->hydrate() t0: " . round($t0 - $t0, 3) . "s\n";
        }
        // Group matches by the hydrator which accepts them
        $groupedRawResults = [];
        foreach ($matches as $i => $match) {
            foreach ($this->hydrators as $hydrator) {
                if ($hydrator->accepts($match)) {
                    $hydratorId = spl_object_id($hydrator);
                    $groupedRawResults[$hydratorId] ??= [];
                    $groupedRawResults[$hydratorId][$i] = $match;
                    break;
                }
            }
        }
        if ($viewer->getId() === 1) {
            $t1 = microtime(true);
            echo "Aggregate->hydrate() t1: " . round($t1 - $t0, 3) . "s\n";
        }

        // Hydrate each result, grouping it into the set of full results
        $fullResults = [];
        foreach ($this->hydrators as $hydrator) {
            $hydratorId = spl_object_id($hydrator);
            if (isset($groupedRawResults[$hydratorId])) {
                if ($viewer->getId() === 1) {
                    $t00 = microtime(true);
                }
                // Rely on $hydrator->hydrate() to preserve the keys from $groupedRawResults[$hydratorId].
                $hydratedResults = $hydrator->hydrate($keyword, $filter, $groupedRawResults[$hydratorId], $viewer);
                $fullResults = $fullResults + $hydratedResults; // Use union operator so keys are preserved.
                if ($viewer->getId() === 1) {
                    $t11 = microtime(true);
                    echo "Aggregate->hydrate() time spent hydrating " . count($groupedRawResults[$hydratorId]) . ' items by ' . get_class($hydrator) . ": " . round($t11 - $t00, 3) . "s\n";
                }
            }
        }
        if ($viewer->getId() === 1) {
            $t2 = microtime(true);
            echo "Aggregate->hydrate() t2: " . round($t2 - $t0, 3) . "s\n";
        }

        // Sort $fullResults by key to ensure the order of results is the same as in $matches.
        ksort($fullResults);
        if ($viewer->getId() === 1) {
            $t3 = microtime(true);
            echo "Aggregate->hydrate() t3: " . round($t3 - $t0, 3) . "s\n";
        }

        // Filter out any results that are null, and flatten
        return array_values(array_filter($fullResults, fn ($x) => isset($x)));
    }
}
