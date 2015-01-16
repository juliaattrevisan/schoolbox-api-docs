<?php
/**
 * Model for side menu items.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SideMenu;

use Schoolbox\Collection;

class Model
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * This menu item's parent (if there is one)
     *
     * @var Model
     */
    protected $parent;

    /**
     * This node's children (if it has any)
     *
     * @var Collection|Model[]
     */
    protected $children;

    /**
     * Menu item text
     *
     * @var string
     */
    protected $text = '';

    /**
     * Menu item URL
     *
     * @var string
     */
    protected $url = '';

    /**
     * Menu link target
     *
     * @var string
     */
    protected $target;

    /**
     * The order in the tree structure that this item exists
     *
     * @var int
     */
    protected $order = 0;

    /**
     * Constructor: build this element with a new, empty collection.
     */
    public function __construct()
    {
        $this->setChildren(new Collection());
    }

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param  int $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param  Model $parent
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @param  Model $child
     * @return $this
     */
    public function addChild($child)
    {
        $this->getChildren()->add($child);
        return $this;
    }

    /**
     * @return Collection|Model[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param  Collection|Model[] $children
     * @return $this
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param  string $text
     *
     * @return $this
     */
    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param  string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param  string $target
     *
     * @return $this
     */
    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
    }
}
