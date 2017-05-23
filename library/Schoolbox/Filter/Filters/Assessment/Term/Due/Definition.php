<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term\Due;

use Schoolbox\Filter\Option as FilterOption;
use \Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;

/**
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

    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            FilterOption\Model::PAGE_PENDING_ASSESSMENTS,
//            FilterOption\Model::PAGE_GRADES,
            FilterOption\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Term/Due/templates';
    }
}
