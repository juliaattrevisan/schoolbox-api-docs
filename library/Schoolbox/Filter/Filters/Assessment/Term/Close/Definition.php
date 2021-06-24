<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term\Close;

use Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;
use Schoolbox\IConfigLoader;

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

    // The name to expose in the UI
    protected const NAME = 'Academic Period Closed';


    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        // Not currently exposed in the UI - used just for internal query building
        return [];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/Term/Close/templates';
    }
}
