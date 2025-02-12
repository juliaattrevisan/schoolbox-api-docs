<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Archived;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page\Model;
use Schoolbox\IConfigLoader;

/**
 * Definition for the action filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Archived Records';

    public const KEY_INCLUDE_ARCHIVED_RECORDS = 'includeArchivedRecords';
    public const KEY_EXCLUDE_ARCHIVED_RECORDS = 'excludeArchivedRecords';

    protected function getCustomKeys(): array
    {
        return [
            self::KEY_EXCLUDE_ARCHIVED_RECORDS,
            self::KEY_INCLUDE_ARCHIVED_RECORDS,
        ];
    }

    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Model::PAGE_CLASS_PASTORAL,
        ];
    }

    public function getLabel(string $key): string
    {
        return match ($key) {
            self::KEY_EXCLUDE_ARCHIVED_RECORDS => 'Exclude Archived Records',
            self::KEY_INCLUDE_ARCHIVED_RECORDS => 'Include Archived Records',
            default => ''
        };
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Pastoral/Archived/templates';
    }
}
