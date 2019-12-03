<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

/**
 * Interface IStatus
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
interface IStatus
{
    /**
     * Decide whether an endpoint can be reached
     *
     * @return bool
     */
    public function isReachable(): bool;


    /**
     * Get a report that explains the current status of the endpoint
     *
     * @return string|null
     */
    public function getReport(): ?string;
}
