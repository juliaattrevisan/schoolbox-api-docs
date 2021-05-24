<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;

/**
 * Mock factory for building an importer out of a CSV.
 *
 * Used in tests to bypass the requirement for a mapper: otherwise, acts
 * identically to `CsvImporterFactory`.
 */
class MockCsvImporterFactory extends AbstractCsvImporterFactory
{
    /**
     * Mapping from slugs to IDs.
     *
     * Made public so that they may be used in tests.
     */
    public const MAPPING = [
        MarkType::SLUG_PERCENT => 1,
        MarkType::SLUG_LETTER  => 2,
        MarkType::SLUG_TOTAL   => 3,
        MarkType::SLUG_NONE    => 6,
    ];


    /**
     * @inheritDoc
     */
    protected function convertMarkTypeSlugToId(?string $slug): ?int
    {
        return self::MAPPING[$slug] ?? null;
    }
}
