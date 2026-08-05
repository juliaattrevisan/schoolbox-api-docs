<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Controller;

class ControllerTest extends TestCase
{
    /**
     * Ensures exporting a rubric does not turn a whitespace-only cell into an empty cell.
     */
    public function testArrayToCsvPreservesWhitespaceOnlyCells(): void
    {
        $controller = new class extends Controller {
            public function __construct()
            {
                // Blank constructor to avoid inheriting unneeded dependencies.
            }

            /**
             * Exposes rubric CSV conversion for testing.
             *
             * @param array $rows
             * @return string
             */
            public function convertToCsv(array $rows): string
            {
                return $this->array2csv($rows);
            }
        };

        $csv = $controller->convertToCsv([
            ['capabilityMarkType: none', 'useMarkRange: Off', 'Level'],
            ['Capability', ' ', 'Descriptor'],
        ]);

        $rows = array_map(
            static fn (string $row): array => str_getcsv($row, escape: ''),
            explode("\n", trim($csv))
        );

        $this->assertSame(' ', $rows[1][1]);
    }
}
