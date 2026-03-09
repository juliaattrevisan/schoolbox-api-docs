<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\My\Folder;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
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
     * This folder's children (if it has any)
     *
     * @todo consider moving to AbstractFolder, and implementing in resource
     *       browser folder mapper/model
     *
     * @var GenericCollection<Model>
     */
    private GenericCollection $children;

    /**
     * Constructor: build this element with a new, empty collection of child
     * folders.
     */
    public function __construct()
    {
        $this->children = new GenericCollection(self::class);
    }

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


    /**
     * @return GenericCollection
     */
    public function getChildren(): GenericCollection
    {
        return $this->children;
    }

    /**
     * @param GenericCollection $children
     * @return $this
     */
    public function setChildren(GenericCollection $children): self
    {
        $this->children = $children;
        return $this;
    }
}
