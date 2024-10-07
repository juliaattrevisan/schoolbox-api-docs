<?php

/**
 *
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Append module name in the log
 */
class ModuleProcessor implements ProcessorInterface
{
    /**
     * Constructor
     *
     * @param string $moduleName The name of the module to log.
     */
    public function __construct(private string $moduleName)
    {
    }

    /**
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra = array_merge(
            $record->extra ?? [],
            ['module' => $this->moduleName,],
        );

        return $record;
    }
}
