<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer;

use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;

/**
 * Factory for building an importer out of a CSV.
 *
 * This implementation relies upon a mapper to translate a mark type slug to an
 * ID.
 */
class CsvImporterFactory extends AbstractCsvImporterFactory
{
    /**
     * The mark type mapper: required so that mark type slugs may be translated
     * to mark types.
     *
     * @var MarkTypeMapper
     */
    protected $markTypeMapper;


    /**
     * Constructor
     *
     * @param MarkTypeMapper $markTypeMapper
     */
    public function __construct(MarkTypeMapper $markTypeMapper)
    {
        $this->markTypeMapper = $markTypeMapper;
    }


    /**
     * @inheritDoc
     */
    protected function convertMarkTypeSlugToId(?string $slug): ?int
    {
        $markType = null;
        if (isset($slug)) {
            $markTypes = $this->markTypeMapper->findIdsByColumnList(['slug' => $slug]);
            if (count($markTypes)) {
                [$markType] = $markTypes;
            }
        }
        return $markType;
    }
}
