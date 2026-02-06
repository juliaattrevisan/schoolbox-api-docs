<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Term;

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
            Page\Model::PAGE_CUSTOMISATION_RECORD,
        ];
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/CustomisationRecord/Term/templates';
    }

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        if (Page\Model::PAGE_CUSTOMISATION_RECORD === $page) {
            return $key === self::KEY_CURRENT;
        }
        return false;
    }

}
