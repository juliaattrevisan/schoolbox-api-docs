<?php
/**
 * Component_Eportfolio_AdvancedList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_AdvancedList extends Component_Eportfolio_List {

	/**
	 * Title of the list item
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Storage hash for an arbitrarily uploaded image
	 *
	 * @var string
	 */
	public $imagehash = '';

	/* Getters/Setters
	 */

	/**
	 * Set image hash
	 *
	 * @param string $imagehash
	 */
	public function setImagehash($imagehash) {
		$this->imagehash = $imagehash;
	}

	/**
	 * Get image hash
	 *
	 * @return string
	 */
	public function getImagehash() {
		return $this->imagehash;
	}

	/**
	 * Set title
	 *
	 * @param string $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * Get title
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}
}