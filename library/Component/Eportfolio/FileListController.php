<?php
/**
 * Component_Eportfolio_FileListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\Permission\Access\UserAccess;

/**
 * @method \Component\Eportfolio\FileListMapper getMapper()
 */
class Component_Eportfolio_FileListController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Component\Eportfolio\FileListMapper::class));
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
        $this->setBlock($templateHandle, 'list_item', 'list_item_block');
        $this->setBlock('list_item', 'has_modify_button', 'has_modify_button_block');
        $this->setBlock('list_item', 'has_unpublish_form', 'has_unpublish_form_block');
        $this->setBlock($templateHandle, 'modify_button', 'modify_button_block');
        $this->setBlock($templateHandle, 'dropzone', 'dropzone_block');

        $this->setVar(
            array(
                'list_item_block' => ''
            )
        );

        $allItems = $this->getMapper()->findForPage($this->getInstance()->getIdentifier());

        /* Show edit buttons
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('dropzone_block', 'dropzone');
            $this->parse('modify_button_block', 'modify_button');
        } else {
            $this->setVar('dropzone_block', '');
            $this->setVar('modify_button_block', '');
        }

        if ($allItems->count()) {
            $j = 0;

            foreach ($allItems as $currentItem) {
                if ($currentItem->getFile()->getMimetype()) {
                    $this->setVar(
                        array(
                            'LIST_ITEM_ID'         => $currentItem->getId(),
                            'FILE_ITEM_ID'         => $currentItem->getFileId(),
                            'FILE_ITEM_TITLE'      => Scrub::text($currentItem->getFile()->getTitle()),
                            'FILE_ITEM_FILENAME'   => Scrub::text($currentItem->getFile()->getFilename()),
                            'FILE_ITEM_MIMETYPE'   => $currentItem->getFile()->getMimetype() ? $currentItem->getFile()->getMimetype()->getIconFile() : '',
                            'FILE_ITEM_CREATED_AT' => $currentItem->getCreatedAt()->getShort(),
                            'ON_OFF'               => ++$j % 2 ? '' : 'dark',
                            'FILE_ITEM_FILESIZE'   => Alaress_Byte_Format::bytesToString($currentItem->getFile()->getSize()),
                        )
                    );

                    /* Controls
                     */
                    if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                        $this->parse('has_unpublish_form_block', 'has_unpublish_form');
                    }

                    $this->parse('list_item_block', 'list_item', true);
                }

                /* Clear children after parsing out the parent
                 */
                $this->setVar('has_unpublish_form_block', '');
            }
        } else {
            $this->setVar('list_item_block', '');
        }

        return true;
    }
}