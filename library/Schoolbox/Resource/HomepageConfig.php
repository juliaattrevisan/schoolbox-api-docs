<?php
/**
 * Homepage config options are all stored in this object
 * It contains things such as which template was initially used to create the
 * homepage, the column widths and colours
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;


class HomepageConfig {
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
	 * @var float
	 */
	protected $leftColumnWidth;


	/**
	 * Width of the right column as a percentage
	 *
	 * @var float
	 */
	protected $rightColumnWidth;

	/**
	 * Font colour
	 *
	 * @var string
	 */
	protected $left_head_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $left_head_bg;

	/**
	 * Font color
	 * @var string
	 */
	protected $left_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $left_bg_primary;

	/**
	 * Font colour
	 *
	 * @var string
	 */
	protected $right_head_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $right_head_bg;

	/**
	 * Font color
	 *
	 * @var string
	 */
	protected $right_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $right_bg_primary;

	/**
	 * Font colour
	 *
	 * @var string
	 */
	protected $top_head_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $top_head_bg;

	/**
	 * Font color
	 *
	 * @var string
	 */
	protected $top_font;

	/**
	 * Background colour
	 *
	 * @var string
	 */
	protected $top_bg_primary;

    /**
     * @param \Schoolbox\Resource\Category $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return \Schoolbox\Resource\Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param float $leftColumnWidth
     * @return $this
     */
    public function setLeftColumnWidth($leftColumnWidth)
    {
        $this->leftColumnWidth = $leftColumnWidth;
        return $this;
    }

    /**
     * @return float
     */
    public function getLeftColumnWidth()
    {
        return $this->leftColumnWidth;
    }

    /**
     * @param string $left_bg_primary
     * @return $this
     */
    public function setLeftBgPrimary($left_bg_primary)
    {
        $this->left_bg_primary = $left_bg_primary;
        return $this;
    }

    /**
     * @return string
     */
    public function getLeftBgPrimary()
    {
        return $this->left_bg_primary;
    }


    /**
     * @param string $left_font
     * @return $this
     */
    public function setLeftFont($left_font)
    {
        $this->left_font = $left_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getLeftFont()
    {
        return $this->left_font;
    }

    /**
     * @param string $left_head_bg
     * @return $this
     */
    public function setLeftHeadBg($left_head_bg)
    {
        $this->left_head_bg = $left_head_bg;
        return $this;
    }

    /**
     * @return string
     */
    public function getLeftHeadBg()
    {
        return $this->left_head_bg;
    }

    /**
     * @param  $left_head_font
     * @return $this
     */
    public function setLeftHeadFont($left_head_font)
    {
        $this->left_head_font = $left_head_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getLeftHeadFont()
    {
        return $this->left_head_font;
    }

    /**
     * @param float $rightColumnWidth
     * @return $this
     */
    public function setRightColumnWidth($rightColumnWidth)
    {
        $this->rightColumnWidth = $rightColumnWidth;
        return $this;
    }

    /**
     * @return float
     */
    public function getRightColumnWidth()
    {
        return $this->rightColumnWidth;
    }

    /**
     * @param  $right_bg_primary
     * @return $this
     */
    public function setRightBgPrimary($right_bg_primary)
    {
        $this->right_bg_primary = $right_bg_primary;
        return $this;
    }

    /**
     * @return string
     */
    public function getRightBgPrimary()
    {
        return $this->right_bg_primary;
    }

    /**
     * @param  $right_font
     * @return $this
     */
    public function setRightFont($right_font)
    {
        $this->right_font = $right_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getRightFont()
    {
        return $this->right_font;
    }

    /**
     * @param  $right_head_bg
     * @return $this
     */
    public function setRightHeadBg($right_head_bg)
    {
        $this->right_head_bg = $right_head_bg;
        return $this;
    }

    /**
     * @return string
     */
    public function getRightHeadBg()
    {
        return $this->right_head_bg;
    }

    /**
     * @param  $right_head_font
     * @return $this
     */
    public function setRightHeadFont($right_head_font)
    {
        $this->right_head_font = $right_head_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getRightHeadFont()
    {
        return $this->right_head_font;
    }

    /**
     * @param HomepageTemplate $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return HomepageTemplate
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param  $top_bg_primary
     * @return $this
     */
    public function setTopBgPrimary($top_bg_primary)
    {
        $this->top_bg_primary = $top_bg_primary;
        return $this;
    }

    /**
     * @return string
     */
    public function getTopBgPrimary()
    {
        return $this->top_bg_primary;
    }

    /**
     * @param  $top_font
     * @return $this
     */
    public function setTopFont($top_font)
    {
        $this->top_font = $top_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getTopFont()
    {
        return $this->top_font;
    }

    /**
     * @param  $top_head_bg
     * @return $this
     */
    public function setTopHeadBg($top_head_bg)
    {
        $this->top_head_bg = $top_head_bg;
        return $this;
    }

    /**
     * @return string
     */
    public function getTopHeadBg()
    {
        return $this->top_head_bg;
    }

    /**
     * @param  $top_head_font
     * @return $this
     */
    public function setTopHeadFont($top_head_font)
    {
        $this->top_head_font = $top_head_font;
        return $this;
    }

    /**
     * @return string
     */
    public function getTopHeadFont()
    {
        return $this->top_head_font;
    }


}