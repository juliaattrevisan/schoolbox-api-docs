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
 * Class Cleanup
 *
 * Remove any stale events
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class Cleanup implements IStrategy
{


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * Cleanup constructor.
     *
     * @param LoggerInterface            $log
     * @param bool                       $perform
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform
    ) {
        $this->log = $log;
        $this->perform = $perform;
    }


    /**
     * Remove stale profiles
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->log->info('Cleanup');
        }
    }
}
