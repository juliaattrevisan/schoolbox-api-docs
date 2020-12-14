<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitSpec;

use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\IConfigLoader;

/**
 * List of unit specs, grouped by Course
 *
 * An assessment will only match a checked unitSpec if it is an assessment spec.
 *
 * □ None (not in a UnitSpec)
 *
 * * CourseSpec A
 * * □ UnitSpec 111 (will match any assessment specs that reside in UnitSpec 111)
 * * □ UnitSpec 222
 *
 * * CourseSpec B
 * * □ UnitSpec 333
 */
class Definition extends NullableContext\Definition
{

    // The name to expose in the UI
    protected const NAME = 'Unit Specification';


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $config): array
    {
        // Does not appear on any pages yet - exists for now just to enable query building
        return [
//            \Schoolbox\Filter\Option\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_NONE === $key) {
            return 'None (assessment does reside inside a unit specification)';
        }
        throw new \InvalidArgumentException("Unknown 'UnitSpec' filter key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/NullableContext/UnitSpec/templates';
    }
}
