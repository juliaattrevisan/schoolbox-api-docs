<?php
/**
 * Component_Eportfolio_BasicList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_BasicList extends Model {

	/**
	 * Primary key
	 *
	 * @var
	 */
	public $list_item_id;

	/**
	 * List to which this list item belongs
	 *
	 * @var
	 */
	public $component_instance_id;

	/**
	 * List item contents
	 *
	 * @var
	 */
	public $content;

	/**
	 * Order of the list item
	 *
	 * @var
	 */
	public $sequence;

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

	/**
	 * Set content
	 *
	 * @param  $content
	 */
	public function setContent($content) {
		$this->content = $content;
	}

	/**
	 * Get content
	 *
	 * @return
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * Set list item id
	 *
	 * @param  $list_item_id
	 */
	public function setListItemId($list_item_id) {
		$this->list_item_id = $list_item_id;
	}

	/**
	 * Get list item id
	 *
	 * @return
	 */
	public function getListItemId() {
		return $this->list_item_id;
	}

	/**
	 * Set sequence
	 *
	 * @param  $sequence
	 */
	public function setSequence($sequence) {
		$this->sequence = $sequence;
	}

	/**
	 * Get sequence
	 *
	 * @return
	 */
	public function getSequence() {
		return $this->sequence;
	}
}