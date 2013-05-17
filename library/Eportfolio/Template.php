<?php
/**
 * Template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;


class Template {

	/**
	 * The primary key
	 *
	 * @var
	 */
	private $id;

	/**
	 * The name of the template
	 *
	 * @var
	 */
	private $name;

	/**
	 * Left column width
	 *
	 * @var
	 */
	private $leftColumnWidth;

	/**
	 * Right column width
	 *
	 * @var
	 */
	private $rightColumnWidth;

	/* Getters/Setters
	 */

	/**
	 * Set id
	 *
	 * @param  $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set left column width
	 *
	 * @param  $leftColumnWidth
	 */
	public function setLeftColumnWidth($leftColumnWidth) {
		$this->leftColumnWidth = $leftColumnWidth;
	}

	/**
	 * Get left column width
	 *
	 * @return
	 */
	public function getLeftColumnWidth() {
		return $this->leftColumnWidth;
	}

	/**
	 * Set name
	 *
	 * @param  $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set right column width
	 *
	 * @param  $rightColumnWidth
	 */
	public function setRightColumnWidth($rightColumnWidth) {
		$this->rightColumnWidth = $rightColumnWidth;
	}

	/**
	 * Get right column width
	 *
	 * @return
	 */
	public function getRightColumnWidth() {
		return $this->rightColumnWidth;
	}
}