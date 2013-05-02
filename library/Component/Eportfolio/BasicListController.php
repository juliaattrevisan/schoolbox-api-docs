<?php
/**
 * Component_Eportfolio_BasicListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_BasicListController extends Component_Eportfolio_ControllerAbstract {

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

	/**
	 * Outputs HTML template defined by config variable 'template'
	 *
	 * @param String $templateHandle
	 * @return bool
	 */
	public function renderTemplate($templateHandle) {
		if (!parent::loadTemplate($templateHandle, $this->getConfig('template'))) {
			return false;
		}

		$this->parseConfig();

		/* Set up blocks and stuff
		 */
		$this->setBlock($templateHandle, 'has_create_form', 'has_create_form_block');
		$this->setBlock($templateHandle, 'list_item', 'list_item_block');
		$this->setBlock('list_item', 'has_modify_button', 'has_modify_button_block');
		$this->setBlock('list_item', 'has_modify_form', 'has_modify_form_block');
		$this->setBlock('list_item', 'has_delete_form', 'has_delete_form_block');

		$this->setVar(array(
			'has_create_form_block' => '',
			'has_modify_form_block' => '',
			'has_delete_form_block' => '',
			'list_item_block' => '',
		));

		/* Controls
		 */
		if ($this->permission >= WRITE_ACCESS) {
			$this->parse('has_create_form_block', 'has_create_form');
		}

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findByColumn('component_instance_id', $this->instance->id);

		if ($allItems->count()) {
			$j = 0;

			foreach ($allItems as $currentItem ) {

				$this->setVar(array(
					'LIST_ITEM_ID' => $currentItem->getId(),
					'LIST_ITEM_CONTENT' => Scrub::text($currentItem->getContent()),
					'ON_OFF' => ++$j % 2 ? '' : 'dark'
				));

				/* Controls
				 */
				if ($this->permission >= WRITE_ACCESS) {
					$this->parse('has_modify_button_block', 'has_modify_button');
					$this->parse('has_modify_form_block', 'has_modify_form');
					$this->parse('has_delete_form_block', 'has_delete_form');
				}

				$this->parse('list_item_block', 'list_item', true);

				/* Clear children after parsing out the parent
				 */
				$this->setVar('has_modify_button_block', '');
				$this->setVar('has_modify_form_block', '');
				$this->setVar('has_delete_form_block', '');
			}
		}

		return true;
	}
}