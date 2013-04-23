<?php
/**
 * Component_Eportfolio_ImageList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Eportfolio_ImageList extends Component_Eportfolio_List {

	/**
	 * Primary key
	 *
	 * @var
	 */
	public $id;

	/**
	 * List to which this list item belongs
	 *
	 * @var
	 */
	public $component_instance_id;

	/* Getters/Setters
	 */

	/**
	 * Set list item id
	 *
	 * @param  $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get list item id
	 *
	 * @return
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set component instance id
	 *
	 * @param  $component_instance_id
	 */
	public function setComponentInstanceId($component_instance_id) {
		$this->component_instance_id = $component_instance_id;
	}

	/**
	 * Get component instance id
	 *
	 * @return
	 */
	public function getComponentInstanceId() {
		return $this->component_instance_id;
	}
}