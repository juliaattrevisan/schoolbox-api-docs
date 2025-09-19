<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;

interface TabTypeInterface
{
    /**
     * Process the records and return the results
     *
     * @param Collection $records
     * @param string|null $encodedCursor
     * @return array
     */
    public function process(Collection $records, ?string $encodedCursor = null): array;
    /**
     * Get the CSV headers for the export
     *
     * @return array
     */
    public function getCSVHeaders(): array;
}
