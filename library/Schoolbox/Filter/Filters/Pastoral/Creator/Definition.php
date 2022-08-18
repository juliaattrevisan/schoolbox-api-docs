<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Creator;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\IConfigLoader;

/**
 * Definition for the creator filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Creator';

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
        return '@filterDefinitions/Pastoral/Creator/templates';
    }
}
