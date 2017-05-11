<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term\Close;

use \Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;


/**
 * Filter to assessments that *CLOSE* in this term.
 *
 * You can pump in custom-built terms with just a end or start date present to filter by this field.
 *
 * Available Term options:
 * □ all
 * □ future
 * □ current
 * □ previous, and
 * □ all terms existing in DB
 *
 * Term options are keyed by 'current', 'previous' and the term IDs
 */
class Definition extends AssessmentTermFilter\Definition
{

    const SLUG = 'tc';


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        // Not currently exposed in the UI - used just for internal query building
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Term/Close/templates';
    }
}
