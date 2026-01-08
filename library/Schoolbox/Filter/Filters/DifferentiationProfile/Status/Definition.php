<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\DifferentiationProfile\Status;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Page\Model;
use Schoolbox\IConfigLoader;

/**
 * Definition for the archive status filter on pastoral records.
 */
class Definition extends AbstractDefinition
{
    protected const NAME = 'Status';

    public const KEY_INCLUDE_OUT_OF_DATE_RECORDS = 'includeOutOfDateRecords';
    public const KEY_INCLUDE_EXPIRED_RECORDS = 'includeExpiredRecords';
    public const KEY_INCLUDE_ACTIVE_RECORDS = 'includeActiveRecords';
    public const KEY_INCLUDE_UNPOPULATED_RECORDS = 'includeUnPopulatedRecords';

    protected function getCustomKeys(): array
    {
        return [
            self::KEY_INCLUDE_ACTIVE_RECORDS,
            self::KEY_INCLUDE_OUT_OF_DATE_RECORDS,
            self::KEY_INCLUDE_EXPIRED_RECORDS,
            self::KEY_INCLUDE_UNPOPULATED_RECORDS,
        ];
    }

    public static function getPages(IConfigLoader $configLoader): array
    {
        return [
            Model::PAGE_DIFFERENTIATION_PROFILE,
        ];
    }

    public function getLabel(string $key): string
    {
        return match ($key) {
            self::KEY_INCLUDE_ACTIVE_RECORDS => 'Active',
            self::KEY_INCLUDE_OUT_OF_DATE_RECORDS => 'Out of Date',
            self::KEY_INCLUDE_EXPIRED_RECORDS => 'Expired',
            self::KEY_INCLUDE_UNPOPULATED_RECORDS => 'Unpopulated',
            default => ''
        };
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/DifferentiationProfile/Status/templates';
    }

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        /* Default the records to `Active` and `Unpopulated` records
         */
        if (
            Model::PAGE_DIFFERENTIATION_PROFILE === $page &&
            ($key === self::KEY_INCLUDE_ACTIVE_RECORDS || $key === self::KEY_INCLUDE_UNPOPULATED_RECORDS)
        ) {
            return true;
        }
        return false;
    }
}
