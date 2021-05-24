<?php

/**
 * Component_Eportfolio_LinkListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

/**
 * @method \Component\Eportfolio\LinkListMapper getMapper()
 */
class Component_Eportfolio_LinkListController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Component\Eportfolio\LinkListMapper::class));
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

        $this->setVar(array(
            'has_create_form_block' => '',
            'has_modify_form_block' => '',
            'has_delete_form_block' => '',
            'list_item_block' => '',
        ));

        /* Controls
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('has_create_form_block', 'has_create_form');
        }

        /* Gather everything
         */
        $allItems = $this->getMapper()->findByColumn('component_instance_id', $this->getInstance()->getId());

        if ($allItems->count()) {
            $j = 0;

            foreach ($allItems as $currentItem) {
                $url = new Url($currentItem->getContent());

                if ($url->isLocalUrl()) {
                    $linkUrl = $url->getRelativeURL();
                    $linkTarget = '';
                } else {
                    $linkUrl = $url->getBase64EncodedRedirect();
                    $linkTarget = 'external';
                }

                $this->setVar(array(
                    'LIST_ITEM_ID'          => Scrub::text($currentItem->getId()),
                    'LIST_ITEM_TITLE'       => Scrub::text($currentItem->getTitle()),
                    'LIST_ITEM_URL'         => Scrub::text($currentItem->getContent()),
                    'LIST_ITEM_ENCODED_URL' => Scrub::text($linkUrl),
                    'LIST_ITEM_TARGET'      => Scrub::text($linkTarget),
                ));

                /* Controls
                 */
                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
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
