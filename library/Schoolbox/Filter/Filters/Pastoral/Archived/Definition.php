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
 * Definition for the archive status filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Status';

    public const KEY_INCLUDE_ARCHIVED_RECORDS = 'includeArchivedRecords';
    public const KEY_INCLUDE_ACTIVE_RECORDS = 'includeActiveRecords';

    protected function getCustomKeys(): array
    {
        return [
            self::KEY_INCLUDE_ACTIVE_RECORDS,
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
            self::KEY_INCLUDE_ACTIVE_RECORDS => 'Active',
            self::KEY_INCLUDE_ARCHIVED_RECORDS => 'Archived',
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

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        if (Model::PAGE_CLASS_PASTORAL === $page && $key === self::KEY_INCLUDE_ACTIVE_RECORDS) {
            return true;
        }
        return false;
    }
}
