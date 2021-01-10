<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\Name;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\IConfigLoader;

/**
 * Definition for the badge name filter: this filter enables filtering of badges
 * by their name or part thereof.
 */
class Definition extends AbstractDefinition
{
    /**
     * @inheritdoc
     */
    protected const NAME = 'Name';

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getPages(IConfigLoader $configLoader): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
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
