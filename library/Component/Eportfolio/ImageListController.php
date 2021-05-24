<?php

/**
 * Component_Eportfolio_ImageListController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

/**
 * @method \Component\Eportfolio\FileListMapper getMapper()
 */
class Component_Eportfolio_ImageListController extends Component_Eportfolio_ControllerAbstract
{
    /**
     * @return bool
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
        $this->setBlock($templateHandle, 'modify_button', 'modify_button_block');
        $this->setBlock($templateHandle, 'dropzone', 'dropzone_block');

        $this->setVar(array(
            'list_item_block' => ''
        ));

        /* Show edit buttons
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('dropzone_block', 'dropzone');
            $this->parse('modify_button_block', 'modify_button');
        } else {
            $this->setVar('dropzone_block', '');
            $this->setVar('modify_button_block', '');
        }

        $allImages = $this->getMapper()->findAllImagesByPage($this->getInstance()->getIdentifier());

        if ($allImages->count()) {
            foreach ($allImages as $currentImage) {
                $this->setVar(array(
                    'IMAGE_ID' => $currentImage->getFileId(),
                    'IMAGE_DESCRIPTION' => Scrub::text('')
                ));

                $this->parse('list_item_block', 'list_item', true);
            }
        }

        return true;
    }
}
