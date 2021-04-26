<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;


/**
 * Class Prepare
 *
 * Clears data from a db in preparation for a Tass API import
 *
 * @package Schoolbox\External\Connector\Tass\Base\Endpoint\Calendar
 */
class Prepare implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var bool
     */
    private $perform;


    /**
     * Prepare constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform
    ) {
        $this->log = $log;
        $this->perform = $perform;
    }


    /**
     * Purge events that were previously imported
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->log->info('Prepare');
        }
    }
}
