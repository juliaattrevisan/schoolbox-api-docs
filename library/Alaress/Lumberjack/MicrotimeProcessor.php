<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MicrotimeProcessor implements ProcessorInterface
{
    /**
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $t = microtime(true);

        $record->extra = array_merge(
            $record->extra ?? [],
            ['microtime' => $t - (int)$t,]
        );

        return $record;
    }
}
