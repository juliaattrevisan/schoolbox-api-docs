<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Office365;

use Schoolbox\Resource\Browser\AbstractNode;

class Node extends AbstractNode
{

    /**
     * @param int    $nodeId
     * @param string $mimeFilter
     * @param Tenant $tenant
     * @param string $oAuthToken
     */
    public function __construct($nodeId, $mimeFilter = null, $tenant = null, $oAuthToken = null)
    {
        // bypass parent node-must-be-numeric check
        $this->setNodeId($nodeId);
        $this->setMimeFilter($mimeFilter);

        // TODO unhide dependency
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