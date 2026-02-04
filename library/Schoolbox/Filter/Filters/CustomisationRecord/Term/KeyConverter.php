<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Term;

use Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;

/**
 * Converts between termFilter concrete keys and Term.ids
 */
class KeyConverter extends AssessmentTermFilter\KeyConverter
{

}
