<?php
/**
 * Component_Eportfolio_TextboxController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_TextboxController extends Component_Eportfolio_ControllerAbstract {

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

		$this->setBlock($templateHandle, 'has_modify_form', 'has_modify_form_block');
		$this->setBlock($templateHandle, 'modify_text', 'modify_text_block');
		$this->setBlock($templateHandle, 'create_text', 'create_text_block');

		$this->setVar(array(
			'text_editable_block' => '',
			'modify_text_block' => '',
			'create_text_block' => '',
			'text_locked_block' => '',
			'has_modify_form_block' => '',
		));

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findByColumn('component_instance_id', $this->instance->id);

		/* If the component instance has a textbox record, render it with Modify capability
		 */
		if ($allItems->count()) {
			$currentItem = $allItems->current();

			$this->setVar(array(
				'TEXTBOX_ITEM_ID' => $this->instance->id,
				'TEXTBOX_CONTENT' => Scrub::html($currentItem->getContent()),
			));

			/* If modifying this textbox
			 */
			if ($this->permission >= WRITE_ACCESS) {
				if (($this->sitepage->arg[2] == 'textbox') && ($this->sitepage->arg[4] == $this->instance->id)) {
					$this->parse('has_modify_form_block', 'has_modify_form');
				}
				else {
					$this->parse('modify_text_block', 'modify_text');
				}
			}
		}
		/* This component instance doesn't have a textbox record yet
		 * so render it with Create capability
		 */
		else {
			$this->setVar(array(
				'TEXTBOX_ITEM_ID' => $this->instance->id,
				'TEXTBOX_CONTENT' => '',
			));

			/* If creating this textbox
			 */
			if ($this->permission >= WRITE_ACCESS) {
				if (($this->sitepage->arg[2] == 'textbox') && ($this->sitepage->arg[4] == $this->instance->id)) {
					$this->parse('has_modify_form_block', 'has_modify_form');
				}
				else {
					$this->parse('create_text_block', 'create_text');
				}
			}
		}

		return true;
	}
}