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

        $this->parseConfig();

        $treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
            Folder\Mapper::getInstance(),
            Schoolbox\Assessment\Course\Unit\DeprecatedMapper::getInstance(),
            $this->getUser(),
            $this->getSitePage()->getLocalisedTerm('resource', true, true)
        );

        $allFolders = $treeBuilder->buildChildNodes($this->getSource()->getId());

        //setup block for submission button
        $this->setVar('FOLDER_CAT_ID', $this->getSource()->getId());

        if (count($allFolders) == 0) {

            $this->setBlock($templateHandle, 'folder', 'folder_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_modify', 'empty_modify_block');

            $this->setVar('folder_block', '');

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar("empty_modify_block", "");
            } else {
                $this->parse("empty_modify_block", "empty_modify");
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            //file output
            $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('source_folder_exists', 'folders', 'folders_block');
            $this->setBlock('source_folder_exists', "submit_folder", "submit_folder_block");
            $this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
            $this->setBlock('folders', 'modify_folder', 'modify_folder_block');

            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                $this->setVar('folders_block', '');

                $maxFolders = $this->getConfig('size');
                if (count($allFolders) && $this->getContentPermission() > UserAccess::ACCESS_DENY) {
                    $j = 0;

                    $this->setVar('folders_block', '');
                    $icons = array('homepage' => 'home', 'folder' => 'folder', 'course' => 'course-builder');

                    foreach ($allFolders as $currentFolder) {
                        //set name
                        $output = array(
                            'FOLDER_ID' => $currentFolder['id'],
                            'FOLDER_NAME' => Scrub::text($currentFolder['text']),
                            'FOLDER_ICON' => $icons[$currentFolder['type']],
                        );
                        if ($maxFolders > 0 && $j > $maxFolders) {
                            break;
                        }

                        $this->setVar($output);

                        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                            $this->parse('modify_folder_block', 'modify_folder');
                        } else {
                            $this->setVar('modify_folder_block', '');
                        }

                        $this->parse('folders_block', 'folders', true);
                    }
                } else {
                    $this->setVar('folders_block', '');
                }

                $this->setVar(
                    array(
                        'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName(),
                        'COMPONENT_CONFIG_SRC_ID' => $this->getSource()->getId(),
                        'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getName(),
                        'COMPONENT_HANDLE' => strtolower($templateHandle)
                    )
                );

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->setVar("submit_folder_block", "");
                } else {
                    $this->parse("submit_folder_block", "submit_folder");
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }
        }

        return true;
    }
}
