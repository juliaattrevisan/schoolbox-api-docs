<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\WebDav;


use InvalidArgumentException;
use Sabre\DAV\Exception\NotAuthenticated;
use Schoolbox\Resource\Browser\AbstractNode;

class Node extends AbstractNode {


    /**
     * @param int  $nodeId
     * @param null $mimeFilter
     * @throws InvalidArgumentException
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