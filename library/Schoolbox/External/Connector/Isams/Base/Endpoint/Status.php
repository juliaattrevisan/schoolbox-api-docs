<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\IStatus;

/**
 * Class Status
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint
 */
class Status implements IStatus
{

    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function isReachable()
    {

        /* Do an an example API request at the given endpoint's host/key settings
         */
    }


    /**
     * @inheritDoc
     *
     * @return string|null
     */
    public function getReport()
    {

        /* Get Specification.php's contents and drop it here
         */
    }
}
