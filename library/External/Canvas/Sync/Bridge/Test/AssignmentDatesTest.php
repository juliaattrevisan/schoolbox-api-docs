<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Test;

use Alaress\DataStructures\Permutations;
use External\Canvas\Sync\Bridge\AssignmentDates;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_push;
use function strcmp;

#[\PHPUnit\Framework\Attributes\CoversClass(\External\Canvas\Sync\Bridge\AssignmentDates::class)]
class AssignmentDatesTest extends TestCase
{
    // Tests
    /**
     * Tests that no matter what permutation (including duplicates) of dates we get from Canvas,
     * we can transform them to a sequence that passes Schoolbox sequential date constraint
     *
     * @param string $publishDateIn
     * @param string $openDateIn
     * @param string $dueDateIn
     * @param string $closeDateIn
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormaliseDatesDistinct')]
    public function testNormaliseDates(string $publishDateIn, string $openDateIn, string $dueDateIn, string $closeDateIn): void
    {
        $assignmentDates = new AssignmentDates();

        [$publishDate, $openDate, $dueDate, $closeDate] = $assignmentDates->normaliseDates($publishDateIn, $openDateIn, $dueDateIn, $closeDateIn, new NullLogger());

        $msg =
            "Dates are invalid.\n" .
            "In: '$publishDateIn' '$openDateIn' '$dueDateIn' '$closeDateIn'.\n" .
            "Out: '$publishDate' '$openDate' '$dueDate' '$closeDate'";

        $this->assertLessThanOrEqual(0, strcmp($publishDate, $openDate), $msg);
        $this->assertLessThanOrEqual(0, strcmp($openDate, $dueDate), $msg);
        $this->assertLessThanOrEqual(0, strcmp($dueDate, $closeDate), $msg);
    }


    // Data Providers


    /**
     * Provide all date permutations (including duplicates)
     * @return array
     */
    public static function providerNormaliseDatesDistinct(): array
    {
        $one   = '2001-01-01 10:00:00';
        $two   = '2002-01-01 10:00:00';
        $three = '2003-01-01 10:00:00';
        $four  = '2004-01-01 10:00:00';

        $permutationGenerator = new Permutations();

        $data = $permutationGenerator->generate([$one, $two, $three, $four]);

        // Set #3 and #4 to have the same value (one duplicate)
        $four = $three;
        array_push($data, ...$permutationGenerator->generateDistinct([$one, $two, $three, $four]));


        // Set #2, #3 and #4 to have the same value (two duplicate)
        $four = $three = $two;
        array_push($data, ...$permutationGenerator->generateDistinct([$one, $two, $three, $four]));

        // Set #1, #2, #3 and #4 to have the same value (three duplicate)
        $four = $three = $two = $one;
        array_push($data, ...$permutationGenerator->generateDistinct([$one, $two, $three, $four]));

        return $data;
    }
}
