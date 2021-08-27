<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Award\Category;

use Schoolbox\Filter\Page;
use Schoolbox\Filter\Filters\Badge\Category as BadgeCategory;
use Schoolbox\IConfigLoader;

/**
 * Definition for badge category filters.
 */
class Definition extends BadgeCategory\Definition
{
    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Page\Model::PAGE_AWARD_LIST,
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Badge/Category/templates';
    }
}
