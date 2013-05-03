<?php
/**
 * Component_Eportfolio_FileListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_FileListController extends Component_Eportfolio_ControllerAbstract {

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
		$mapper = new \Component\Eportfolio\FileListMapper();

		$this->setMapperClass($mapper);

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
		$this->setBlock('list_item', 'has_unpublish_form', 'has_unpublish_form_block');

		$this->setVar(array(
			'list_item_block' => ''
		));

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findForInstance($this->instance->id);

		if ($allItems->count()) {
			$j = 0;
			
			foreach ($allItems as $currentItem) {
				$this->setVar(array(
					'LIST_ITEM_ID' => $currentItem->getId(),
					'FILE_ITEM_ID' => $currentItem->getFileId(),
					'FILE_ITEM_TITLE' => Scrub::text($currentItem->getFile()->getDescription()),
					'FILE_ITEM_FILENAME' => Scrub::text($currentItem->getFile()->getFilename()),
					'FILE_ITEM_MIMETYPE' => $currentItem->getFile()->getMime()->getClass(),
					'FILE_ITEM_CREATED_AT' => $currentItem->getCreatedAt()->getShort(),
					'ON_OFF' => ++$j % 2 ? '' : 'dark',
					'FILE_ITEM_FILESIZE' => Alaress_Byte_Format::bytesToString($currentItem->getFile()->getFilesize()),
				));

				/* Controls
				 */
				if ($this->permission >= WRITE_ACCESS) {
					$this->parse('has_unpublish_form_block', 'has_unpublish_form');
				}

				$this->parse('list_item_block', 'list_item', true);

				/* Clear children after parsing out the parent
				 */
				$this->setVar('has_unpublish_form_block', '');
			}
		}
		else {
			$this->setVar('list_item_block', '');
		}

		return true;
	}
}