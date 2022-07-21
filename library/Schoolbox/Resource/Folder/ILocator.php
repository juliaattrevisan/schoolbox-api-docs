<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Schoolbox\Collection;

interface ILocator
{
    /**
     * Return the folder with the given ID
     *
     * @param int $nodeId
     * @return IFolder|null
     */
    public function findById($nodeId);


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    public function findByParentId($nodeId);
}
