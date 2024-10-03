<?php

/**
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\Resource\Folder\Model as Category;

/**
 * Class HomepageConfig
 *
 * Homepage config options are all stored in this object
 * It contains things such as which template was initially used to create the
 * homepage, the column widths and colours
 *
 * @package Schoolbox\Resource
 */
class HomepageConfig
{
    /**
     * @var Category
     */
    protected $category;

    /**
     * The template originally used to create the page
     *
     * @var HomepageTemplate
     */
    protected $template;

    /**
     * Width of the left column as a percentage
     *
     * @var float|null
     */
    protected $leftColumnWidth;


    /**
     * Width of the right column as a percentage
     *
     * @var float|null
     */
    protected $rightColumnWidth;

    /**
     * Font colour
     *
     * @var string|null
     */
    protected $left_head_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $left_head_bg;

    /**
     * Font color
     * @var string|null
     */
    protected $left_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $left_bg_primary;

    /**
     * Font colour
     *
     * @var string|null
     */
    protected $right_head_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $right_head_bg;

    /**
     * Font color
     *
     * @var string|null
     */
    protected $right_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $right_bg_primary;

    /**
     * Font colour
     *
     * @var string|null
     */
    protected $top_head_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $top_head_bg;

    /**
     * Font color
     *
     * @var string|null
     */
    protected $top_font;

    /**
     * Background colour
     *
     * @var string|null
     */
    protected $top_bg_primary;

    /**
     * @param Category $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param null|float $leftColumnWidth
     * @return $this
     */
    public function setLeftColumnWidth(?float $leftColumnWidth): self
    {
        $this->leftColumnWidth = $leftColumnWidth;
        return $this;
    }

    /**
     * @return null|float
     */
    public function getLeftColumnWidth(): ?float
    {
        return $this->leftColumnWidth;
    }

    /**
     * @param string|null $left_bg_primary
     * @return $this
     */
    public function setLeftBgPrimary($left_bg_primary)
    {
        $this->left_bg_primary = $left_bg_primary;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLeftBgPrimary()
    {
        return $this->left_bg_primary;
    }


    /**
     * @param string|null $left_font
     * @return $this
     */
    public function setLeftFont($left_font)
    {
        $this->left_font = $left_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLeftFont()
    {
        return $this->left_font;
    }

    /**
     * @param string|null $left_head_bg
     * @return $this
     */
    public function setLeftHeadBg($left_head_bg)
    {
        $this->left_head_bg = $left_head_bg;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLeftHeadBg()
    {
        return $this->left_head_bg;
    }

    /**
     * @param string|null $left_head_font
     * @return $this
     */
    public function setLeftHeadFont($left_head_font)
    {
        $this->left_head_font = $left_head_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLeftHeadFont()
    {
        return $this->left_head_font;
    }

    /**
     * @param null|float $rightColumnWidth
     * @return $this
     */
    public function setRightColumnWidth(?float $rightColumnWidth): self
    {
        $this->rightColumnWidth = $rightColumnWidth;
        return $this;
    }

    /**
     * @return null|float
     */
    public function getRightColumnWidth(): ?float
    {
        return $this->rightColumnWidth;
    }

    /**
     * @param string|null $right_bg_primary
     * @return $this
     */
    public function setRightBgPrimary($right_bg_primary)
    {
        $this->right_bg_primary = $right_bg_primary;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRightBgPrimary()
    {
        return $this->right_bg_primary;
    }

    /**
     * @param string|null $right_font
     * @return $this
     */
    public function setRightFont($right_font)
    {
        $this->right_font = $right_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRightFont()
    {
        return $this->right_font;
    }

    /**
     * @param string|null $right_head_bg
     * @return $this
     */
    public function setRightHeadBg($right_head_bg)
    {
        $this->right_head_bg = $right_head_bg;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRightHeadBg()
    {
        return $this->right_head_bg;
    }

    /**
     * @param string|null $right_head_font
     * @return $this
     */
    public function setRightHeadFont($right_head_font)
    {
        $this->right_head_font = $right_head_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRightHeadFont()
    {
        return $this->right_head_font;
    }

    /**
     * @param HomepageTemplate|null $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return HomepageTemplate|null
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string|null $top_bg_primary
     * @return $this
     */
    public function setTopBgPrimary($top_bg_primary)
    {
        $this->top_bg_primary = $top_bg_primary;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTopBgPrimary()
    {
        return $this->top_bg_primary;
    }

    /**
     * @param string|null $top_font
     * @return $this
     */
    public function setTopFont($top_font)
    {
        $this->top_font = $top_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTopFont()
    {
        return $this->top_font;
    }

    /**
     * @param string|null $top_head_bg
     * @return $this
     */
    public function setTopHeadBg($top_head_bg)
    {
        $this->top_head_bg = $top_head_bg;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTopHeadBg()
    {
        return $this->top_head_bg;
    }

    /**
     * @param string|null $top_head_font
     * @return $this
     */
    public function setTopHeadFont($top_head_font)
    {
        $this->top_head_font = $top_head_font;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTopHeadFont()
    {
        return $this->top_head_font;
    }
}
