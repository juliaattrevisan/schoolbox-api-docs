<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitSpec;

use Schoolbox\Filter\Filters\Assessment\NullableContext;

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
    const SLUG = 'us';

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
        if(self::KEY_NONE === $key){
            return 'None (assessment does reside inside a unit specification)';
        }
        throw new \InvalidArgumentException("Unknown 'UnitSpec' filter key: $key");
    }
}
