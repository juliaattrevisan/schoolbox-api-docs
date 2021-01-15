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
     * Must be empty, so that the ID finder knows that an empty string is
     * distinct from an actual search term.
     */
    public const KEY_ALL = '';

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
        return [Page\Model::PAGE_BADGE_LIST,];
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
