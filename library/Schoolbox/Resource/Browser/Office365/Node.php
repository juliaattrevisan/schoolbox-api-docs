<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Office365;

use RuntimeException;
use Schoolbox\Resource\Browser\AbstractNode;

class Node extends AbstractNode
{

    /**
     * @param int         $nodeId
     * @param string|null $mimeFilter
     * @param Tenant|null $tenant
     * @param string|null $oAuthToken
     * @throws RuntimeException
     */
    public function __construct($nodeId, $mimeFilter = null, $tenant = null, $oAuthToken = null)
    {
        // bypass parent node-must-be-numeric check
        $this->setNodeId($nodeId);
        $this->setMimeFilter($mimeFilter);

        // TODO unhide dependency
        if (!$tenant instanceof Tenant) {
            throw new RuntimeException("No tenant supplied");
        }
        $locator = new Locator($tenant, $oAuthToken);

        $this->setFileLocator($locator);
        $this->setFolderLocator($locator);
    }


    /**
     * Returns whether or not permission can be granted for the given node
     *
     * @param int $nodeId
     * @param int $minimumLevel
     * @return bool
     */
    protected function hasPermission($nodeId, $minimumLevel)
    {
        return true;
    }
}
