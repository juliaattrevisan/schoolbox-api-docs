<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\Name;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\IConfigLoader;

/**
 * Definition for the badge name filter: this filter enables filtering of badges
 * by their name or part thereof.
 */
class Definition extends AbstractDefinition
{
    /**
     * Key for finding all badges.
     *
     * Required so that the badge list filter is even configurable.
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
    protected const NAME = 'Name';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [self::KEY_ALL];
    }

    /**
     * This filter is available on the badge list page.
     *
     * @param IConfigLoader $configLoader
     * @return array
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_BADGE_LIST,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if ($key === self::KEY_ALL) {
            return 'All badges';
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/Name/templates';
    }
}
