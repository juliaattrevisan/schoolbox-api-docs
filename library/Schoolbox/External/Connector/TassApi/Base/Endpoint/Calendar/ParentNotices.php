<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Importer\Endpoint\IStrategy;


/**
 * Class ParentNotices
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class ParentNotices implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var Api
     */
    private $api;


    /**
     * ParentNotices constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param array           $api
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
    }


    /**
     *
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->log->info('ParentNotices');
            // TODO: Implement import() method.
        }
    }
}