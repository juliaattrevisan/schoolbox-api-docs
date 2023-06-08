<?php

/**
 *
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

/**
 * Append module name in the log
 */
class ModuleProcessor
{
    /**
     */
    public function __construct(private string $moduleName)
    {
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        $record['extra'] = array_merge(
            $record['extra'],
            [
                'module' => $this->moduleName,
            ]
        );

        return $record;
    }
}
