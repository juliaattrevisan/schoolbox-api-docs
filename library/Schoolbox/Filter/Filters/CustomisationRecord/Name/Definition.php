<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Name;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\IConfigLoader;

/**
 * Definition for the customisation record student name filter: this filter enables filtering of
 * students by their name or part thereof.
 */
class Definition extends AbstractDefinition
{
    /**
     * Key for finding all students.
     *
     * Required so that the customisation record list filter is even configurable.
     *
     * This must be a term that will not be readily searched for, as entering
     * this string as an actual search term will bypass the name filter
     * completely.
     *
     * Setting this to an empty string does not currently work, for two reasons:
     * * the filter option with an empty string key is not inserted properly
     *   into a collection object
     *   (this is fixable by modifying the collection's `offsetSet` method)
     * * the empty string key, when used in an input's name, is translated to 0
     *   when deserialized into a POST var
     *   (not fixable without reworking the filter admin form)
     */
    public const KEY_ALL = '---';

    /**
     * @inheritdoc
     */
    protected const NAME = 'Student';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_ALL];
    }

    /**
     * This filter is available on the student list page.
     *
     * @param IConfigLoader $configLoader
     * @return array
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
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_ALL) {
            return 'All students';
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/CustomisationRecord/Name/templates';
    }
}
