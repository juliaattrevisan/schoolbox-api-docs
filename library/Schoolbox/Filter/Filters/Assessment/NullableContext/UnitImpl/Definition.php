<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImpl;

use Schoolbox\Filter\Filters\Assessment\NullableContext;

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

    const SLUG = 'ui';


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        // Does not appear on any pages yet - exists for now just to enable query building
        return [
//            \Schoolbox\Filter\Option\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        if (self::KEY_NONE === $key) {
            return 'None (assessment does reside inside a unit implementation)';
        }
        throw new \InvalidArgumentException("Unknown 'UnitImpl' filter key: $key");
    }
}
