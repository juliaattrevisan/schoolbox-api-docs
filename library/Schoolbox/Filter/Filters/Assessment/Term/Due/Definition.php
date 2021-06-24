<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term\Due;

use Schoolbox\Filter\Page;
use Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;
use Schoolbox\IConfigLoader;

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
    protected const NAME = 'Academic Period';


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_PENDING_ASSESSMENTS,
//            Page\Model::PAGE_GRADES,
            Page\Model::PAGE_CLASS_MARK_BOOK,
            Page\Model::PAGE_COURSE_MARK_BOOK,
            Page\Model::PAGE_RUBRIC_MARK_BOOK,
//            Page\Model::PAGE_UPCOMING_ASSESSMENTS,
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/Term/Due/templates';
    }
}
