<?php
/**
 * Eportfolio_Page
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Eportfolio;

class Page {

	/**
	 * The id of the page currently being viewed
	 *
	 * @var
	 */
	public $page_id;

	/**
	 * The page title (appears in window drag bar)
	 *
	 * @var
	 */
	public $page_title;

	/**
	 * The owner of the page
	 *
	 * @var
	 */
	public $uid;

	/**
	 * The template the page is using
	 *
	 * @var
	 */
	public $template;

	/**
	 * Width of the left column as a percentage
	 *
	 * @var
	 */
	public $left_column_width;

	/**
	 * Font colour
	 *
	 * @var
	 */
	public $left_head_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $left_head_bg;

	/**
	 * Font color
	 * @var
	 */
	public $left_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $left_bg_primary;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $left_bg_secondary;

	/**
	 * Link colour
	 *
	 * @var
	 */
	public $left_link;

	/**
	 * Hover colour
	 *
	 * @var
	 */
	public $left_hover;

	/**
	 * Width of the right column as a percentage
	 *
	 * @var
	 */
	public $right_column_width;

	/**
	 * Font colour
	 *
	 * @var
	 */
	public $right_head_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $right_head_bg;

	/**
	 * Font color
	 *
	 * @var
	 */
	public $right_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $right_bg_primary;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $right_bg_secondary;

	/**
	 * Link colour
	 *
	 * @var
	 */
	public $right_link;

	/**
	 * Hover colour
	 *
	 * @var
	 */
	public $right_hover;

	/**
	 * Font colour
	 *
	 * @var
	 */
	public $top_head_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $top_head_bg;

	/**
	 * Font color
	 *
	 * @var
	 */
	public $top_font;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $top_bg_primary;

	/**
	 * Background colour
	 *
	 * @var
	 */
	public $top_bg_secondary;

	/**
	 * Link colour
	 *
	 * @var
	 */
	public $top_link;

	/**
	 * Hover colour
	 *
	 * @var
	 */
	public $top_hover;

	/**
	 * Created at
	 *
	 * @var
	 */
	private $created_at;

	/**
	 * Updated at
	 *
	 * @var
	 */
	private $updated_at;

	/**
	 * Deleted at
	 *
	 * @var
	 */
	private $deleted_at;

	/**
	 * A collection of permissions
	 *
	 * @var
	 */
	private $permissions;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setPermissions(new \Schoolbox\Collection());
	}

	/* Getters/Setters
	 */

	/**
	 * Set colour
	 *
	 * @param $left_bg_primary
	 */
	public function setLeftBgPrimary($left_bg_primary) {
		$this->left_bg_primary = $left_bg_primary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftBgPrimary() {
		return $this->left_bg_primary;
	}

	/**
	 * Set colour
	 *
	 * @param $left_bg_secondary
	 */
	public function setLeftBgSecondary($left_bg_secondary) {
		$this->left_bg_secondary = $left_bg_secondary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftBgSecondary() {
		return $this->left_bg_secondary;
	}

	/**
	 * Set colour
	 *
	 * @param $left_column_width
	 */
	public function setLeftColumnWidth($left_column_width) {
		$this->left_column_width = $left_column_width;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftColumnWidth() {
		return $this->left_column_width;
	}

	/**
	 * Set colour
	 *
	 * @param $left_font
	 */
	public function setLeftFont($left_font) {
		$this->left_font = $left_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftFont() {
		return $this->left_font;
	}

	/**
	 * Set colour
	 *
	 * @param $left_head_bg
	 */
	public function setLeftHeadBg($left_head_bg) {
		$this->left_head_bg = $left_head_bg;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftHeadBg() {
		return $this->left_head_bg;
	}

	/**
	 * Set colour
	 *
	 * @param $left_head_font
	 */
	public function setLeftHeadFont($left_head_font) {
		$this->left_head_font = $left_head_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftHeadFont() {
		return $this->left_head_font;
	}

	/**
	 * Set colour
	 *
	 * @param $left_hover
	 */
	public function setLeftHover($left_hover) {
		$this->left_hover = $left_hover;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftHover() {
		return $this->left_hover;
	}

	/**
	 * Set colour
	 *
	 * @param $left_link
	 */
	public function setLeftLink($left_link) {
		$this->left_link = $left_link;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getLeftLink() {
		return $this->left_link;
	}

	/**
	 * Set page id
	 *
	 * @param $page_id
	 */
	public function setPageId($page_id) {
		$this->page_id = $page_id;
	}

	/**
	 * Get page id
	 *
	 * @return mixed
	 */
	public function getPageId() {
		return $this->page_id;
	}

	/**
	 * Set page title
	 *
	 * @param $page_title
	 */
	public function setPageTitle($page_title) {
		$this->page_title = $page_title;
	}

	/**
	 * Get page title
	 *
	 * @return mixed
	 */
	public function getPageTitle() {
		return $this->page_title;
	}

	/**
	 * Set colour
	 *
	 * @param $right_bg_primary
	 */
	public function setRightBgPrimary($right_bg_primary) {
		$this->right_bg_primary = $right_bg_primary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightBgPrimary() {
		return $this->right_bg_primary;
	}

	/**
	 * Set colour
	 *
	 * @param $right_bg_secondary
	 */
	public function setRightBgSecondary($right_bg_secondary) {
		$this->right_bg_secondary = $right_bg_secondary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightBgSecondary() {
		return $this->right_bg_secondary;
	}

	/**
	 * Set colour
	 *
	 * @param $right_column_width
	 */
	public function setRightColumnWidth($right_column_width) {
		$this->right_column_width = $right_column_width;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightColumnWidth() {
		return $this->right_column_width;
	}

	/**
	 * Set colour
	 *
	 * @param $right_font
	 */
	public function setRightFont($right_font) {
		$this->right_font = $right_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightFont() {
		return $this->right_font;
	}

	/**
	 * Set colour
	 *
	 * @param $right_head_bg
	 */
	public function setRightHeadBg($right_head_bg) {
		$this->right_head_bg = $right_head_bg;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightHeadBg() {
		return $this->right_head_bg;
	}

	/**
	 * Set colour
	 *
	 * @param $right_head_font
	 */
	public function setRightHeadFont($right_head_font) {
		$this->right_head_font = $right_head_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightHeadFont() {
		return $this->right_head_font;
	}

	/**
	 * Set colour
	 *
	 * @param $right_hover
	 */
	public function setRightHover($right_hover) {
		$this->right_hover = $right_hover;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightHover() {
		return $this->right_hover;
	}

	/**
	 * Set colour
	 *
	 * @param $right_link
	 */
	public function setRightLink($right_link) {
		$this->right_link = $right_link;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getRightLink() {
		return $this->right_link;
	}

	/**
	 * Set colour
	 *
	 * @param $template
	 */
	public function setTemplate($template) {
		$this->template = $template;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTemplate() {
		return $this->template;
	}

	/**
	 * Set colour
	 *
	 * @param $top_bg_primary
	 */
	public function setTopBgPrimary($top_bg_primary) {
		$this->top_bg_primary = $top_bg_primary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopBgPrimary() {
		return $this->top_bg_primary;
	}

	/**
	 * Set colour
	 *
	 * @param $top_bg_secondary
	 */
	public function setTopBgSecondary($top_bg_secondary) {
		$this->top_bg_secondary = $top_bg_secondary;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopBgSecondary() {
		return $this->top_bg_secondary;
	}

	/**
	 * Set colour
	 *
	 * @param $top_font
	 */
	public function setTopFont($top_font) {
		$this->top_font = $top_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopFont() {
		return $this->top_font;
	}

	/**
	 * Set colour
	 *
	 * @param $top_head_bg
	 */
	public function setTopHeadBg($top_head_bg) {
		$this->top_head_bg = $top_head_bg;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopHeadBg() {
		return $this->top_head_bg;
	}

	/**
	 * Set colour
	 *
	 * @param $top_head_font
	 */
	public function setTopHeadFont($top_head_font) {
		$this->top_head_font = $top_head_font;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopHeadFont() {
		return $this->top_head_font;
	}

	/**
	 * Set colour
	 *
	 * @param $top_hover
	 */
	public function setTopHover($top_hover) {
		$this->top_hover = $top_hover;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopHover() {
		return $this->top_hover;
	}

	/**
	 * Set colour
	 *
	 * @param $top_link
	 */
	public function setTopLink($top_link) {
		$this->top_link = $top_link;
	}

	/**
	 * Get colour
	 *
	 * @return mixed
	 */
	public function getTopLink() {
		return $this->top_link;
	}

	/**
	 * Set uid
	 *
	 * @param $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * Get uid
	 *
	 * @return mixed
	 */
	public function getUid() {
		return $this->uid;
	}

	/**
	 * Set created at
	 *
	 * @param $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}

	/**
	 * Get created at
	 *
	 * @return mixed
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set deleted at
	 *
	 * @param $deleted_at
	 */
	public function setDeletedAt($deleted_at) {
		$this->deleted_at = $deleted_at;
	}

	/**
	 * Get deleted at
	 *
	 * @return mixed
	 */
	public function getDeletedAt() {
		return $this->deleted_at;
	}

	/**
	 * Set updated at
	 *
	 * @param $updated_at
	 */
	public function setUpdatedAt($updated_at) {
		$this->updated_at = $updated_at;
	}

	/**
	 * Get updated at
	 *
	 * @return mixed
	 */
	public function getUpdatedAt() {
		return $this->updated_at;
	}

	/**
	 * Set permissions
	 *
	 * @param  $permissions
	 */
	public function setPermissions($permissions) {
		$this->permissions = $permissions;
	}

	/**
	 * Get permissions
	 *
	 * @return
	 */
	public function getPermissions() {
		return $this->permissions;
	}
}