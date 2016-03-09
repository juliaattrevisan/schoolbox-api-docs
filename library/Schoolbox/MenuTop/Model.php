<?php
/**
 * Model for top menu items.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\MenuTop;

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
     * Menu item text
     *
     * @var string
     */
    protected $title = '';

    /**
     * Icon
     *
     * @var string
     */
    protected $icon = '';

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
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param  string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $title;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param  string $icon
     *
     * @return $this
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
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
