<?php
/**
 * Component_Eportfolio_ListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_ListController extends Component_Eportfolio_ControllerAbstract {

	/**
	 * A mapper instance, for polymorphism
	 *
	 * @var null
	 */
	protected $mapperClass = null;

	/**
	 * Prepares data for the render function. Business logic.
	 *
	 * Override this in subclasses
	 *
	 * @return void
	 */
	public function execute() {
		$this->setMapperClass(Component_Eportfolio_BasicListMapper::getInstance());

		return true;
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $mapperClass
	 */
	protected function setMapperClass($mapperClass) {
		$this->mapperClass = $mapperClass;
	}

	/**
	 * @return null
	 */
	protected function getMapperClass() {
		return $this->mapperClass;
	}
}