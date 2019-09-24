<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\Filter;

/**
 * TODO re-word to clarify
 *
 * A NullableContext filter is an assessment-context filter that shows a '(none)' option
 *
 * They have a coupling to the 'Folder' filter, as the 'Folder' is the ultimate context.
 *
 * We need to treat them specially because by default they do not show a full list
 *
 * If no options are selected, we can set the filter to NULL, knowing that the folder filer will then ensure no irrelevant
 * assessments are returned.
 *
 * If just the (none) option is selected, we return an empty array to indicate only assessments that do NOT live in this context
 * should be returned.
 *
 * If both the (none) and some other options are selected, then the idFinder needs to treat it as empty for other filters, but not empty for this filter.
 * This will ensure other sub-queries will run, but we will UNION those with the concrete items in this filter
 */
abstract class Definition extends Filter\AbstractDefinition
{

    public const KEY_NONE = 'none';  // match assessments that do NOT reside in this context


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_NONE,
        ];
    }
}
