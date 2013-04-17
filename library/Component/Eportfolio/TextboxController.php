<?php
/**
 * Component_Eportfolio_TextboxController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_TextboxController extends Component_Eportfolio_ListController {

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
		$this->setMapperClass(Component_Eportfolio_TextboxMapper::getInstance());

		return true;
	}

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * Note that textboxes have a 1:1 relationship with their component instance
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, $this->getConfig('template'))) {
			return false;
		}

		$this->parseConfig();

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findByColumn('component_instance_id', $this->instance->id);

		if ($allItems->count()) {
			for ($allItems->rewind(); $allItems->valid(); $allItems->next()) {
				$currentItem = $allItems->current();

				$this->setVar(array(
					'TEXTBOX_ITEM_ID' => $this->instance->id,
					'TEXTBOX_CONTENT' => $currentItem->getContent(),
				));
			}
		}
		else {
			$this->setVar(array(
				'TEXTBOX_ITEM_ID' => $this->instance->id,
				'TEXTBOX_CONTENT' => ''
			));
		}

		return true;
	}
}