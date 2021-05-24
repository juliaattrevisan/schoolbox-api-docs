<?php

/**
 * Eportfolio_Page
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;

class Page implements ISoftDeletable
{

    /**
     * The id of the page currently being viewed
     *
     * @var int
     */
    private $page_id;

    /**
     * The page title (appears in window drag bar)
     *
     * @var string
     */
    private $page_title;

    /**
     * The owner of the page
     *
     * @var User
     */
    private $owner;


    /**
     * The template the page is using
     *
     * @var int
     */
    private $template;

    /**
     * Width of the left column as a percentage
     *
     * @var float
     */
    private $left_column_width;

    /**
     * Font colour
     *
     * @var string
     */
    private $left_head_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $left_head_bg;

    /**
     * Font color
     * @var string
     */
    private $left_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $left_bg_primary;

    /**
     * Width of the right column as a percentage
     *
     * @var float
     */
    private $right_column_width;

    /**
     * Font colour
     *
     * @var string
     */
    public $right_head_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $right_head_bg;

    /**
     * Font color
     *
     * @var string
     */
    private $right_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $right_bg_primary;

    /**
     * Font colour
     *
     * @var string
     */
    private $top_head_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $top_head_bg;

    /**
     * Font color
     *
     * @var string
     */
    private $top_font;

    /**
     * Background colour
     *
     * @var string
     */
    private $top_bg_primary;


    /**
     * Created at
     *
     * @var \Alaress_Date_Format
     */
    private $created_at;

    /**
     * Updated at
     *
     * @var \Alaress_Date_Format
     */
    private $updated_at;

    /**
     * Deleted at
     *
     * @var \Alaress_Date_Format|null
     */
    private $deleted_at;

    /**
     * A collection of permissions
     *
     * @var GenericCollection<Permission>|Permission[]
     */
    private $permissions;

    /* Getters/Setters
     */

    /**
     * Set colour
     *
     * @param string $left_bg_primary
     */
    public function setLeftBgPrimary($left_bg_primary)
    {
        $this->left_bg_primary = $left_bg_primary;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getLeftBgPrimary()
    {
        return $this->left_bg_primary;
    }

    /**
     * Set colour
     *
     * @param float $left_column_width
     */
    public function setLeftColumnWidth($left_column_width)
    {
        $this->left_column_width = $left_column_width;
    }

    /**
     * Get colour
     *
     * @return float
     */
    public function getLeftColumnWidth()
    {
        return $this->left_column_width;
    }

    /**
     * Set colour
     *
     * @param string $left_font
     */
    public function setLeftFont($left_font)
    {
        $this->left_font = $left_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getLeftFont()
    {
        return $this->left_font;
    }

    /**
     * Set colour
     *
     * @param string $left_head_bg
     */
    public function setLeftHeadBg($left_head_bg)
    {
        $this->left_head_bg = $left_head_bg;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getLeftHeadBg()
    {
        return $this->left_head_bg;
    }

    /**
     * Set colour
     *
     * @param string $left_head_font
     */
    public function setLeftHeadFont($left_head_font)
    {
        $this->left_head_font = $left_head_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getLeftHeadFont()
    {
        return $this->left_head_font;
    }

    /**
     * Set page id
     *
     * @param int $page_id
     */
    public function setPageId($page_id)
    {
        $this->page_id = $page_id;
    }

    /**
     * Get page id
     *
     * @return int
     */
    public function getPageId()
    {
        return $this->page_id;
    }

    /**
     * Set page title
     *
     * @param string $page_title
     */
    public function setPageTitle($page_title)
    {
        $this->page_title = $page_title;
    }

    /**
     * Get page title
     *
     * @return string
     */
    public function getPageTitle()
    {
        return $this->page_title;
    }

    /**
     * Set colour
     *
     * @param string $right_bg_primary
     */
    public function setRightBgPrimary($right_bg_primary)
    {
        $this->right_bg_primary = $right_bg_primary;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getRightBgPrimary()
    {
        return $this->right_bg_primary;
    }

    /**
     * Set colour
     *
     * @param float $right_column_width
     */
    public function setRightColumnWidth($right_column_width)
    {
        $this->right_column_width = $right_column_width;
    }

    /**
     * Get colour
     *
     * @return float
     */
    public function getRightColumnWidth()
    {
        return $this->right_column_width;
    }

    /**
     * Set colour
     *
     * @param string $right_font
     */
    public function setRightFont($right_font)
    {
        $this->right_font = $right_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getRightFont()
    {
        return $this->right_font;
    }

    /**
     * Set colour
     *
     * @param string $right_head_bg
     */
    public function setRightHeadBg($right_head_bg)
    {
        $this->right_head_bg = $right_head_bg;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getRightHeadBg()
    {
        return $this->right_head_bg;
    }

    /**
     * Set colour
     *
     * @param string $right_head_font
     */
    public function setRightHeadFont($right_head_font)
    {
        $this->right_head_font = $right_head_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getRightHeadFont()
    {
        return $this->right_head_font;
    }

    /**
     * Set colour
     *
     * @param int $template
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Get colour
     *
     * @return int
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set colour
     *
     * @param string $top_bg_primary
     */
    public function setTopBgPrimary($top_bg_primary)
    {
        $this->top_bg_primary = $top_bg_primary;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getTopBgPrimary()
    {
        return $this->top_bg_primary;
    }

    /**
     * Set colour
     *
     * @param string $top_font
     */
    public function setTopFont($top_font)
    {
        $this->top_font = $top_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getTopFont()
    {
        return $this->top_font;
    }

    /**
     * Set colour
     *
     * @param string $top_head_bg
     */
    public function setTopHeadBg($top_head_bg)
    {
        $this->top_head_bg = $top_head_bg;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getTopHeadBg()
    {
        return $this->top_head_bg;
    }

    /**
     * Set colour
     *
     * @param string $top_head_font
     */
    public function setTopHeadFont($top_head_font)
    {
        $this->top_head_font = $top_head_font;
    }

    /**
     * Get colour
     *
     * @return string
     */
    public function getTopHeadFont()
    {
        return $this->top_head_font;
    }


    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return $this
     */
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Set created at
     *
     * @param \Alaress_Date_Format $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * Get created at
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deleted_at
     */
    public function setDeletedAt($deleted_at)
    {
        $this->deleted_at = $deleted_at;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format|null
     */
    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

    /**
     * Set updated at
     *
     * @param \Alaress_Date_Format $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    /**
     * Get updated at
     *
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Set permissions
     *
     * @param GenericCollection<Permission> $permissions
     * @return $this
     */
    public function setPermissions(GenericCollection $permissions): self
    {
        $permissions->assertCollectionOf(Permission::class);
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Get permissions
     *
     * @return Collection|Permission[]
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }
}
