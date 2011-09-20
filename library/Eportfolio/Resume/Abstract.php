<?php
/**
 * Eportfolio_Resume_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Resume_Abstract extends Eportfolio_Objective_Abstract {
	public $_table = 'eportfolio_comp';
	public $_index = 'id';

	/* One will come - the componentHandle from the Outer World...bringing this
	 * component out of darkness.
	 *
	 * http://www.youtube.com/watch?v=-tcKtZ_AtEU&feature=player_detailpage#t=67s
	 */
	protected $componentHandle = null;

	protected $allComponents = array(array());

	/**
	 * Set up template and blocks used in the resume
	 *
	 * @param  $allComponents
	 * @return void
	 */
	protected function setupBlocks() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplate());

		foreach ($this->getAllComponents() as $currentComponent) {
			$this->getPage()->setBlock($this->getComponentHandle(), $currentComponent['block'], $currentComponent['block'] . '_block');
		}
	}

	/**
	 * Create a resume out of Objective components
	 *
	 * @return void
	 */
	public function render() {
		$this->setupBlocks();

		foreach($this->getAllComponents() as $currentComponent) {
			$instance = $this->getInstanceFromMethod($currentComponent['method']);
			$instance->setUserId($instance->getPage()->arg[0]);

			$component = $instance->getByField('user_id', $instance->getUserId());

			if ($component) {
				$this->getPage()->setVar(array(
					'CONTENT' => $component['body']
				));

				$this->getPage()->parse($currentComponent['block'] . '_block', $currentComponent['block'], true);
			}
			else {
				$this->getPage()->setVar($currentComponent['block'] . '_block', '');
			}
		}
	}

	/**
	 * Factory method which generates a class via a method name found
	 * in the eportfolio_comp table
	 *
	 * @param  $method
	 * @return
	 */
	protected function getInstanceFromMethod($method) {
		$record = $this->getByField('method', $method);

		$className = str_replace('outputObjective', 'Eportfolio_Objective_', $record['method']);

		return new $className($this->getPage(), null);
	}

	/* Getters/setters
	 */

	/**
	 * Get the components used in this resume
	 *
	 * @return
	 */
	protected function getAllComponents() {
		return $this->allComponents;
	}

	/**
	 * Get component handle
	 *
	 * @return null
	 */
	protected function getComponentHandle() {
		return $this->componentHandle;
	}

	/**
	 * Set component handle
	 *
	 * @param  $val
	 * @return void
	 */
	public function setComponentHandle($val) {
		$this->componentHandle = $val;
	}

	/**
	 * Get the template used for this resume
	 *
	 * @return string
	 */
	protected function getTemplate() {
		return $this->template;
	}
}
