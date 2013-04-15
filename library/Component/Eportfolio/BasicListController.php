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
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
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
		$this->setBlock($templateHandle, 'list_item', 'list_item_block');

		/* Gather everything
		 */
		$allItems = Component_Eportfolio_BasicListMapper::getInstance()->findByColumn('component_instance_id', 2920);

		if ($allItems->count()) {
			for ($allItems->rewind(); $allItems->valid(); $allItems->next()) {
				$currentItem = $allItems->current();

				$this->setVar(array(
					'LIST_ITEM_CONTENT' => $currentItem->getContent()
				));

				$this->parse('list_item_block', 'list_item', true);
			}
		}

        return true;
    }
}
