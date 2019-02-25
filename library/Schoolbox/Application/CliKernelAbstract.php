<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application;

use Database;

/**
 * Common implementation for all Cli Kernels
 */
abstract class CliKernelAbstract extends \Alaress\Application\Kernel
{

    /**
     * @inheritdoc
     */
    protected function initialiseDebugging(): void
    {
        // no debugging
    }


    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     * @throws \Schoolbox\External\Exception
     */
    protected function initialiseExternalConnections(): void
    {
        // MySQL connection configuration
        Database::getInstance()->connect(SQL_dsn, SQL_user, SQL_passwd, ['throw' => true]);
    }
}
