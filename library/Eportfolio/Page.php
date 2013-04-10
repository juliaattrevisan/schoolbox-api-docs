<?php
/**
 * Eportfolio_Page
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Page {

	/**
	 * @var
	 */
	public $page_id;

	/**
	 * @var
	 */
	public $page_title;

	/**
	 * @var
	 */
	public $uid;

	/**
	 * @var
	 */
	public $template;

	/**
	 * @var
	 */
	public $left_column_width;

	/**
	 * @var
	 */
	public $left_head_font;

	/**
	 * @var
	 */
	public $left_head_bg;

	/**
	 * @var
	 */
	public $left_font;

	/**
	 * @var
	 */
	public $left_bg_primary;

	/**
	 * @var
	 */
	public $left_bg_secondary;

	/**
	 * @var
	 */
	public $left_link;

	/**
	 * @var
	 */
	public $left_hover;

	/**
	 * @var
	 */
	public $right_column_width;

	/**
	 * @var
	 */
	public $right_head_font;

	/**
	 * @var
	 */
	public $right_head_bg;

	/**
	 * @var
	 */
	public $right_font;

	/**
	 * @var
	 */
	public $right_bg_primary;

	/**
	 * @var
	 */
	public $right_bg_secondary;

	/**
	 * @var
	 */
	public $right_link;

	/**
	 * @var
	 */
	public $right_hover;

	/**
	 * @var
	 */
	public $top_head_font;

	/**
	 * @var
	 */
	public $top_head_bg;

	/**
	 * @var
	 */
	public $top_font;

	/**
	 * @var
	 */
	public $top_bg_primary;

	/**
	 * @var
	 */
	public $top_bg_secondary;

	/**
	 * @var
	 */
	public $top_link;

	/**
	 * @var
	 */
	public $top_hover;

	/* Getters/Setters
	 */

	/**
	 * @param $left_bg_primary
	 */
	public function setLeftBgPrimary($left_bg_primary) {
		$this->left_bg_primary = $left_bg_primary;
	}

	/**
	 * @return mixed
	 */
	public function getLeftBgPrimary() {
		return $this->left_bg_primary;
	}

	/**
	 * @param $left_bg_secondary
	 */
	public function setLeftBgSecondary($left_bg_secondary) {
		$this->left_bg_secondary = $left_bg_secondary;
	}

	/**
	 * @return mixed
	 */
	public function getLeftBgSecondary() {
		return $this->left_bg_secondary;
	}

	/**
	 * @param $left_column_width
	 */
	public function setLeftColumnWidth($left_column_width) {
		$this->left_column_width = $left_column_width;
	}

	/**
	 * @return mixed
	 */
	public function getLeftColumnWidth() {
		return $this->left_column_width;
	}

	/**
	 * @param $left_font
	 */
	public function setLeftFont($left_font) {
		$this->left_font = $left_font;
	}

	/**
	 * @return mixed
	 */
	public function getLeftFont() {
		return $this->left_font;
	}

	/**
	 * @param $left_head_bg
	 */
	public function setLeftHeadBg($left_head_bg) {
		$this->left_head_bg = $left_head_bg;
	}

	/**
	 * @return mixed
	 */
	public function getLeftHeadBg() {
		return $this->left_head_bg;
	}

	/**
	 * @param $left_head_font
	 */
	public function setLeftHeadFont($left_head_font) {
		$this->left_head_font = $left_head_font;
	}

	/**
	 * @return mixed
	 */
	public function getLeftHeadFont() {
		return $this->left_head_font;
	}

	/**
	 * @param $left_hover
	 */
	public function setLeftHover($left_hover) {
		$this->left_hover = $left_hover;
	}

	/**
	 * @return mixed
	 */
	public function getLeftHover() {
		return $this->left_hover;
	}

	/**
	 * @param $left_link
	 */
	public function setLeftLink($left_link) {
		$this->left_link = $left_link;
	}

	/**
	 * @return mixed
	 */
	public function getLeftLink() {
		return $this->left_link;
	}

	/**
	 * @param $page_id
	 */
	public function setPageId($page_id) {
		$this->page_id = $page_id;
	}

	/**
	 * @return mixed
	 */
	public function getPageId() {
		return $this->page_id;
	}

	/**
	 * @param $page_title
	 */
	public function setPageTitle($page_title) {
		$this->page_title = $page_title;
	}

	/**
	 * @return mixed
	 */
	public function getPageTitle() {
		return $this->page_title;
	}

	/**
	 * @param $right_bg_primary
	 */
	public function setRightBgPrimary($right_bg_primary) {
		$this->right_bg_primary = $right_bg_primary;
	}

	/**
	 * @return mixed
	 */
	public function getRightBgPrimary() {
		return $this->right_bg_primary;
	}

	/**
	 * @param $right_bg_secondary
	 */
	public function setRightBgSecondary($right_bg_secondary) {
		$this->right_bg_secondary = $right_bg_secondary;
	}

	/**
	 * @return mixed
	 */
	public function getRightBgSecondary() {
		return $this->right_bg_secondary;
	}

	/**
	 * @param $right_column_width
	 */
	public function setRightColumnWidth($right_column_width) {
		$this->right_column_width = $right_column_width;
	}

	/**
	 * @return mixed
	 */
	public function getRightColumnWidth() {
		return $this->right_column_width;
	}

	/**
	 * @param $right_font
	 */
	public function setRightFont($right_font) {
		$this->right_font = $right_font;
	}

	/**
	 * @return mixed
	 */
	public function getRightFont() {
		return $this->right_font;
	}

	/**
	 * @param $right_head_bg
	 */
	public function setRightHeadBg($right_head_bg) {
		$this->right_head_bg = $right_head_bg;
	}

	/**
	 * @return mixed
	 */
	public function getRightHeadBg() {
		return $this->right_head_bg;
	}

	/**
	 * @param $right_head_font
	 */
	public function setRightHeadFont($right_head_font) {
		$this->right_head_font = $right_head_font;
	}

	/**
	 * @return mixed
	 */
	public function getRightHeadFont() {
		return $this->right_head_font;
	}

	/**
	 * @param $right_hover
	 */
	public function setRightHover($right_hover) {
		$this->right_hover = $right_hover;
	}

	/**
	 * @return mixed
	 */
	public function getRightHover() {
		return $this->right_hover;
	}

	/**
	 * @param $right_link
	 */
	public function setRightLink($right_link) {
		$this->right_link = $right_link;
	}

	/**
	 * @return mixed
	 */
	public function getRightLink() {
		return $this->right_link;
	}

	/**
	 * @param $template
	 */
	public function setTemplate($template) {
		$this->template = $template;
	}

	/**
	 * @return mixed
	 */
	public function getTemplate() {
		return $this->template;
	}

	/**
	 * @param $top_bg_primary
	 */
	public function setTopBgPrimary($top_bg_primary) {
		$this->top_bg_primary = $top_bg_primary;
	}

	/**
	 * @return mixed
	 */
	public function getTopBgPrimary() {
		return $this->top_bg_primary;
	}

	/**
	 * @param $top_bg_secondary
	 */
	public function setTopBgSecondary($top_bg_secondary) {
		$this->top_bg_secondary = $top_bg_secondary;
	}

	/**
	 * @return mixed
	 */
	public function getTopBgSecondary() {
		return $this->top_bg_secondary;
	}

	/**
	 * @param $top_font
	 */
	public function setTopFont($top_font) {
		$this->top_font = $top_font;
	}

	/**
	 * @return mixed
	 */
	public function getTopFont() {
		return $this->top_font;
	}

	/**
	 * @param $top_head_bg
	 */
	public function setTopHeadBg($top_head_bg) {
		$this->top_head_bg = $top_head_bg;
	}

	/**
	 * @return mixed
	 */
	public function getTopHeadBg() {
		return $this->top_head_bg;
	}

	/**
	 * @param $top_head_font
	 */
	public function setTopHeadFont($top_head_font) {
		$this->top_head_font = $top_head_font;
	}

	/**
	 * @return mixed
	 */
	public function getTopHeadFont() {
		return $this->top_head_font;
	}

	/**
	 * @param $top_hover
	 */
	public function setTopHover($top_hover) {
		$this->top_hover = $top_hover;
	}

	/**
	 * @return mixed
	 */
	public function getTopHover() {
		return $this->top_hover;
	}

	/**
	 * @param $top_link
	 */
	public function setTopLink($top_link) {
		$this->top_link = $top_link;
	}

	/**
	 * @return mixed
	 */
	public function getTopLink() {
		return $this->top_link;
	}

	/**
	 * @param $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * @return mixed
	 */
	public function getUid() {
		return $this->uid;
	}



}