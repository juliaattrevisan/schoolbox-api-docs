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
		$this->setBlock($templateHandle, 'create_text', 'create_text_block');
		$this->setBlock('list_item', 'modify_text', 'modify_text_block');
		$this->setBlock('list_item', 'has_modify_form', 'has_modify_form_block');
		$this->setBlock('list_item', 'has_delete_form', 'has_delete_form_block');
		$this->setBlock('list_item', 'has_comments', 'has_comments_block');
		$this->setBlock('has_comments', 'comment', 'comment_block');

		$this->setVar(array(
			'has_create_form_block' => '',
			'has_modify_form_block' => '',
			'has_delete_form_block' => '',
			'modify_text_block' => '',
			'create_text_block' => '',
			'has_comments_block' => '',
			'comment_block' => '',
			'list_item_block' => '',
		));

		/* Controls
		 */
		if ($this->permission >= WRITE_ACCESS) {
			if ($this->sitepage->arg[3] == 'create') {
				$this->parse('has_create_form_block', 'has_create_form');
			}
			else {
				$this->parse('create_text_block', 'create_text');
			}
		}

		/* Gather everything
		 */
		$allItems = $this->getMapperClass()->findByOrder(array(
			'identifierName' => 'page_id',
			'identifierValue' => $this->instance->identifier,
			'column' => 'updated_at',
			'order' => 'DESC'
		));

		if ($allItems->count()) {
			foreach($allItems as $currentItem) {
				$updatedAt = new Alaress_Date_Format($currentItem->getUpdatedAt(), 'y-m-d h:i:s');

				$this->setVar(array(
					'LIST_ITEM_ID' => $currentItem->getId(),
					'LIST_ITEM_TITLE' => $currentItem->getTitle(),
					'LIST_ITEM_CONTENT' => $currentItem->getContent(),
					'LIST_ITEM_UPDATED_AT' => $updatedAt->getShort(),
				));

				$allComments = Component_Eportfolio_BlogCommentMapper::getInstance()->findByColumn('blog_id', $currentItem->getId());

				if ($allComments->count()) {
					foreach($allComments as $currentComment) {
						$commentAuthor = NewUserMapper::getInstance()->findById($currentComment->getUid());

						$this->setVar(array(
							'COMMENT_EXTERNAL_USER_ID' => $commentAuthor->getSynergyId(),
							'COMMENT_AUTHOR_FIRSTNAME' => $commentAuthor->getFirstname(),
							'COMMENT_AUTHOR_LASTNAME' => $commentAuthor->getLastname(),
							'COMMENT_CONTENT' => $currentComment->getContent(),
						));

						$this->parse('comment_block', 'comment', true);
					}

					$this->parse('has_comments_block', 'has_comments');

					/* Clear children after parsing out the parent
					 */
					$this->setVar('comment_block', '');
				}

				/* If modifying this textbox
				 */
				if ($this->permission >= WRITE_ACCESS) {
					if (($this->sitepage->arg[2] == 'blog') && ($this->sitepage->arg[3] == 'modify') &&  ($this->sitepage->arg[4] == $currentItem->getId())) {
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
				$this->setVar('has_comments_block', '');
				$this->setVar('comment_block', '');
			}
		}

		return true;
	}
}