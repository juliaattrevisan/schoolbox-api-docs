<?php
/**
 * Component_Eportfolio_LinkList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_LinkList extends Component_Eportfolio_List {

	/**
	 * Human readable version of the link
	 * $content is the actual url itself
	 *
	 * @var string
	 */
	public $title = '';

	/* Getters/Setters
	 */

	/**
	 * Set title
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