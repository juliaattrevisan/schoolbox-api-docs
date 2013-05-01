<?php
/**
 * Component_Eportfolio_BlogController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_BlogController extends Component_Eportfolio_ControllerAbstract {

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
		$this->setMapperClass(Component_Eportfolio_BlogMapper::getInstance());

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
		$this->setBlock('list_item', 'modify_text', 'modify_text_block');
		$this->setBlock('list_item', 'create_text', 'create_text_block');
		$this->setBlock('list_item', 'has_modify_form', 'has_modify_form_block');
		$this->setBlock('list_item', 'has_delete_form', 'has_delete_form_block');

		$this->setVar(array(
			'has_create_form_block' => '',
			'has_modify_form_block' => '',
			'has_delete_form_block' => '',
			'modify_text_block' => '',
			'create_text_block' => '',
			'list_item_block' => '',
		));

		/* Controls
		 */
		if ($this->permission >= WRITE_ACCESS) {
			$this->parse('has_create_form_block', 'has_create_form');
		}

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findByColumn('page_id', $this->instance->identifier);

		if ($allItems->count()) {
			foreach($allItems as $currentItem) {
				$updatedAt = new Alaress_Date_Format($currentItem->getUpdatedAt(), 'y-m-d h:i:s');

				$this->setVar(array(
					'LIST_ITEM_TITLE' => $currentItem->getTitle(),
					'LIST_ITEM_CONTENT' => $currentItem->getContent(),
					'LIST_ITEM_UPDATED_AT' => $updatedAt->getShort(),
				));

				/* If modifying this textbox
				 */
				if ($this->permission >= WRITE_ACCESS) {
					if (($this->sitepage->arg[2] == 'blog') && ($this->sitepage->arg[4] == $this->instance->id)) {
						$this->parse('has_modify_form_block', 'has_modify_form');
					}
					else {
						$this->parse('has_delete_form_block', 'has_delete_form');
						$this->parse('modify_text_block', 'modify_text');
					}
				}

				$this->parse('list_item_block', 'list_item', true);

				/* Clear children after parsing out the parent
				 */
				$this->setVar('has_image_block', '');
				$this->setVar('has_modify_form_block', '');
				$this->setVar('has_delete_form_block', '');
				$this->setVar('modify_text_block', '');
				$this->setVar('create_text_block', '');
			}
		}

		return true;
	}
}