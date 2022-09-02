<?php

/**
 * Component_Eportfolio_AdvancedListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Zone;

/**
 * @method \Component\Eportfolio\AdvancedListMapper getMapper()
 */
class Component_Eportfolio_AdvancedListController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Component\Eportfolio\AdvancedListMapper::class));
        return true;
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
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
        $this->setBlock('list_item', 'has_image', 'has_image_block');
        $this->setBlock('list_item', 'no_image', 'no_image_block');
        $this->setBlock('has_modify_form', 'has_image_modify', 'has_image_modify_block');

        $this->setVar(array(
            'has_create_form_block' => '',
            'has_modify_form_block' => '',
            'has_delete_form_block' => '',
            'has_image_block' => '',
            'no_image_block' => '',
            'list_item_block' => '',
        ));

        /* Controls
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $zone = (new Zone('imagehashCreate'.$this->getInstance()->getId()))
                ->setFileVar('imagehash[]')
                ->setThumbnailSize(64)
                ->setModalTitle('Select Images')
                ->setAttachmentsLabel('')
                ->setAttachPrompt('Upload Images')
                ->setMimeFilter('image/*')
                ->setReadOnly(false)
                ->setMulti(false);
            $zone->renderIntoSitePage($this->getSitePage(), 'IMAGEHASH_CREATE_ATTACH_ZONE');
            $this->parse('has_create_form_block', 'has_create_form');
        }

        /* Gather everything
         */
        $instance_id = $this->getInstance()->getId();
        $allItems = $this->getMapper()->findByColumn('component_instance_id', $instance_id);

        if ($allItems->count()) {
            foreach ($allItems as $currentItem) {
                $this->setVar(array(
                    'LIST_ITEM_ID' => $currentItem->getId(),
                    'LIST_ITEM_TITLE' => Scrub::text($currentItem->getTitle()),
                    'LIST_ITEM_CONTENT' => Scrub::html($currentItem->getContent()),
                ));

                /* Parse out the image if one was assigned
                 */
                if (strlen($currentItem->getImagehash())) {
                    $this->setVar(array(
                        'LIST_ITEM_IMAGEHASH' => $currentItem->getImagehash(),
                    ));

                    $this->parse('has_image_block', 'has_image');
                } else {
                    $this->parse('no_image_block', 'no_image');
                }

                /* Controls
                 */
                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->parse('has_modify_button_block', 'has_modify_button');
                    $zone = (new Zone('imagehashModify'.$this->getInstance()->getId().'-'.$currentItem->getId()) )
                        ->setFileVar('imagehash[]')
                        ->setThumbnailSize(64)
                        ->setModalTitle('Select Images')
                        ->setAttachmentsLabel('')
                        ->setAttachPrompt('Upload Images')
                        ->setMimeFilter('image/*')
                        ->attachFiles([$currentItem->getImageHash()])
                        ->setReadOnly(false)
                        ->setMulti(false);
                    if (strlen($currentItem->getImagehash())) {
                        $zone->attachFiles([$currentItem->getImageHash()]);
                    }
                    $zone->renderIntoSitePage($this->getSitePage(), 'IMAGEHASH_MODIFY_ATTACH_ZONE');
                    $this->parse('has_modify_form_block', 'has_modify_form');
                    $this->parse('has_delete_form_block', 'has_delete_form');
                }

                $this->parse('list_item_block', 'list_item', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar('has_image_block', '');
                $this->setVar('no_image_block', '');
                $this->setVar('has_modify_button_block', '');
                $this->setVar('has_modify_form_block', '');
                $this->setVar('has_delete_form_block', '');
                $this->setVar('has_image_modify_block', '');
            }
        }

        return true;
    }
}
