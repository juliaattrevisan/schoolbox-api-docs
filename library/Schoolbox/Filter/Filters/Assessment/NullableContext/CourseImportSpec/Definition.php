<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\CourseImportSpec;

use InvalidArgumentException;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\Filter\Page;

/**
 * The course spec that an an assessment's course implementation was imported
 * from.
 *
 * 'Give me assessments in a [project inside a] course implementation, that was
 *  imported from course spec X'
 *
 * An assessment created in a course implementation, but not imported from a
 * course spec, is still considered to be 'imported' from that spec.
 *
 * We need to retain '(none)' to enable a full set of options for this filter
 * Eg:
 *
 * * (none) (is not part of an imported course)
 * * CourseSpec A
 * * CourseSpec B
 */
class Definition extends NullableContext\Definition
{
    // The name to expose in the UI
    protected const NAME = "Course Specification";


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Page\Model::PAGE_ASSESSMENT_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_NONE) {
            return '(none)';
        }
        throw new InvalidArgumentException("Unknown 'CourseSpec' filter key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/NullableContext/CourseImportSpec/templates';
    }
}
