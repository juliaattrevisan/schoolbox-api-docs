<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term\Due;

use Schoolbox\Filter\Page;
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

    // The name to expose in the UI
    const NAME = 'Term Due';


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Page\Model::PAGE_PENDING_ASSESSMENTS,
//            Page\Model::PAGE_GRADES,
            Page\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/Term/Due/templates';
    }
}
