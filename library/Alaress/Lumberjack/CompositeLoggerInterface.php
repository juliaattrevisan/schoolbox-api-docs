<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Lumberjack;

use Psr\Log\LoggerInterface;

/**
 * Interface for all loggers which are composed of several loggers.
 *
 * It is the responsibility of implementing classes to ensure that `log()`
 * proxies through to all loggers in this logger.
 *
 * @package Alaress\Lumberjack
 */
interface CompositeLoggerInterface extends LoggerInterface
{
    /**
     * Adds another logger to this logger.
     *
     * @param LoggerInterface $log
     * @return self
     */
    public function pushComposite(LoggerInterface $log);
}
