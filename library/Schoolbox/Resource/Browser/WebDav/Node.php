<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\WebDav;

use InvalidArgumentException;
use Schoolbox\Resource\Browser\AbstractNode;

class Node extends AbstractNode
{


    /**
     * @param int         $nodeId
     * @param string|null $mimeFilter
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        parent::__construct($nodeId, $mimeFilter);

        $dav = \PortfolioWebDAV::factory(true);

        if (!$dav) {
            throw new InvalidArgumentException("Could not connect to WebDAV");
        }

        $this->setFileLocator($dav);
        $this->setFolderLocator($dav);
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
