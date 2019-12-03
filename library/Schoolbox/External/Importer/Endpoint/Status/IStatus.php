<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Status;

/**
 * Interface IStatus
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
interface IStatus
{
    /**
     * Decide whether an endpoint can be reached
     * (Do an an example API request at the given endpoint's host/key settings)
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
