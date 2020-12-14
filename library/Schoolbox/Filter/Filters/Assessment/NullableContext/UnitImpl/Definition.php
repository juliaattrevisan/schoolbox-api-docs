<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImpl;

use InvalidArgumentException;
use Schoolbox\Filter\Filters\Assessment\NullableContext;
use Schoolbox\IConfigLoader;

/**
 * List of unit impls, grouped by Course Impl
 *
 * An assessment will only match a checked unitImpl if it is an assessment impl.
 *
 * □ None (not in a UnitImpl)
 *
 * * CourseImpl A
 * * □ UnitImpl 111 (will match any assessment impls that reside in UnitImpl 111)
 * * □ UnitImpl 222
 *
 * * CourseImpl B
 * * □ UnitImpl 333
 */
class Definition extends NullableContext\Definition
{

    // The name to expose in the UI
    protected const NAME = 'Unit Implementation';


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
            return 'None (assessment does reside inside a unit implementation)';
        }
        throw new InvalidArgumentException("Unknown 'UnitImpl' filter key: $key");
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/NullableContext/UnitImpl/templates';
    }
}
