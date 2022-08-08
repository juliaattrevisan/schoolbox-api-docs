<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\My\Folder;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\AbstractFolder;
use Schoolbox\Resource\Browser\NodeInterface;
use Schoolbox\User;

/**
 * Class Model
 *
 * The MyFolder Model. Represents a folder within the portfolio system.
 *
 * @package Schoolbox\Resource\My\Folder
 */
class Model extends AbstractFolder implements ISoftDeletable
{
    /** @var User */
    protected $owner;


    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }


    /**
     * @inheritdoc
     */
    public function getNodeType(): string
    {
        return NodeInterface::TYPE_FOLDER;
    }
}
