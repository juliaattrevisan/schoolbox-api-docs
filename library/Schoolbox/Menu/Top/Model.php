<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Top;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /** @var int */
    protected $sortOrder;

    /** @var string */
    protected $title;

    /** @var string */
    protected $text;

    /** @var string */
    protected $token;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $url;

    /** @var boolean */
    protected $webmail;

    /** @var string */
    protected $target;

    /** @var boolean */
    protected $planHide;


    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
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
     * @param string $text
     * @return self
     */
    public function setText($text)
    {
        $this->text = $text;
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
     * @param string $token
     * @return self
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
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
     * @param string $webmail
     * @return self
     */
    public function setWebmail($webmail)
    {
        $this->webmail = $webmail;
        return $this;
    }

    /**
     * @return string
     */
    public function getWebmail()
    {
        return $this->webmail;
    }

    /**
     * @param string $target
     * @return self
     */
    public function setTarget($target)
    {
        $this->target = $target;
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
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @return int
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * @return bool
     */
    public function getPlanHide(): bool
    {
        return $this->planHide;
    }

    /**
     * @param bool $planHide
     * @return self
     */
    public function setPlanHide(bool $planHide): self
    {
        $this->planHide = $planHide;
        return $this;
    }
}
