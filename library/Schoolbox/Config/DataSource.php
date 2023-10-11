<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Config;

use Alaress\Database\Connector\IConnector;

/**
 * Data source for configuration.
 */
class DataSource
{
    /**
     * Find all config options that are available within Schoolbox.
     *
     * @param IConnector $database The database from which to load configs.
     * @return array
     */
    public function findAllConfigs(IConnector $database): array
    {
        $query   = $database->executeQuery('SELECT name, value, field_id FROM config');
        $configs = [];
        foreach ($query as $row) {
            $configs[$row['name']] = $this->hydrateValue((int)$row['field_id'], $row['value']);
        }
        return $configs;
    }

    /**
     * Light-weight hydration to respect boolean fields
     *
     * @param int   $fieldId
     * @param mixed $value
     * @return mixed
     */
    private function hydrateValue(int $fieldId, mixed $value): mixed
    {
        switch ($fieldId) {
            case ConfigMapper::FIELD_BOOLEAN:
            case ConfigMapper::FIELD_CHECKBOX:
                return $value === null ? null : (bool)$value;
            default:
        }
        return $value;
    }
}
