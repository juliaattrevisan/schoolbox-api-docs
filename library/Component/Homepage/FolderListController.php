<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder;
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_FolderListController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'folderList')) {
            return false;
        }
        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_folder', 'has_folder_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
            Folder\Mapper::getInstance(),
            $this->getUser(),
            $this->getSitePage()->getLocalisedTerm('resource', true, true)
        );

        $allFolders = $treeBuilder->buildChildNodes($this->getSource()->getId());

        //setup block for submission button
        $this->setVar('FOLDER_CAT_ID', $this->getSource()->getId());

        if (count($allFolders) == 0 || $this->getContentPermission() == UserAccess::ACCESS_DENY) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_folder_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //file output
            $this->setBlock('has_folder', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_folder', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'grid_view', 'grid_view_block');
            $this->setBlock('source_folder_exists', 'list_view', 'list_view_block');
            $this->setBlock('source_folder_exists', "submit_folder", "submit_folder_block");

            if ($this->getConfig('displayPortraits') == 'grid') {
                $this->setBlock('grid_view', 'grid_folders', 'grid_folders_block');
                $this->setBlock('grid_folders', 'modify_folder', 'modify_folder_block');

            } else {
                $this->setBlock('list_view', 'list_folders', 'list_folders_block');
                $this->setBlock('list_folders', 'modify_folder', 'modify_folder_block');
            }

            $this->setVar('list_view_block', '');
            $this->setVar('list_folders_block','');
            $this->setVar('grid_view_block', '');
            $this->setVar('grid_folders_block', '');
            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {

                $this->setVar('source_folder_deleted_block', '');

                $maxFolders = $this->getConfig('size');
                $j = 0;
                $colourIndex = 0;
                $colourList = Timetable_ColourMapper::getInstance()->findAll();

                foreach ($allFolders as $currentFolder) {

                    if ($maxFolders > 0 && $j >= $maxFolders) {
                        break;
                    }

                    /**
                     * Set folder colour and cover image for grid layout
                     */
                    if ($this->getConfig('displayPortraits') == 'grid') {

                        /**
                         * Check for folder colour and apply colour from list if no colour exists
                         */
                        if (empty($currentFolder['colour'])) {
                            $folderColour = $colourList[$colourIndex]->getHexColour();
                            $colourIndex = ($colourIndex + 1) % $colourList->count();
                        } else {
                            $folderColour = Scrub::text($currentFolder['colour']);
                        }

                        /**
                         * Check for folder cover image
                         */
                        if ($currentFolder['coverImage'] instanceof Schoolbox\Resource\Storage\Model) {
                            $folderCoverImage = 'background-image: url(\'/storage/image.php?hash=' . $currentFolder['coverImage']->getHash() . '&size=cardImageLarge\'); background-size: cover; background-repeat: no-repeat; background-position: center;';
                        } else {
                            $folderCoverImage = '';
                        }

                    } else {
                        $folderColour = '';
                        $folderCoverImage = '';
                    }

                    $output = array(
                        'FOLDER_ID' => $currentFolder['id'],
                        'FOLDER_NAME' => Scrub::text($currentFolder['text']),
                        'FOLDER_ICON' => $currentFolder['type'],
                        'FOLDER_COLOUR' => $folderColour,
                        'FOLDER_COVER_IMAGE' => $folderCoverImage,
                    );

                    $this->setVar($output);

                    if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                        $this->parse('modify_folder_block', 'modify_folder');
                    } else {
                        $this->setVar('modify_folder_block', '');
                    }

                    if ($this->getConfig('displayPortraits') == 'grid') {
                        $this->parse('grid_folders_block', 'grid_folders', true);
                    } else {
                        $this->parse('list_folders_block', 'list_folders', true);
                    }

                    $j++;
                }

                if ($this->getConfig('displayPortraits') == 'grid') {
                    $this->parse('grid_view_block', 'grid_view');
                } else {
                    $this->parse('list_view_block', 'list_view');
                }


                $this->setVar([
                    'COMPONENT_NAME' => strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : $this->getComponentName(),
                    'COMPONENT_CONFIG_SRC_ID' => $this->getSource()->getId(),
                    'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getName(),
                    'COMPONENT_HANDLE' => strtolower($templateHandle)
                ]);

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar("submit_folder_block", "");
                } else {
                    $this->parse("submit_folder_block", "submit_folder");
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_folder_block', 'has_folder');
        }

        return true;
    }
}
