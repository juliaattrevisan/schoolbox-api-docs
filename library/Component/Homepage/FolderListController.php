<?php
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FolderListController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * An instance of Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $source;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();

        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        $this->source = $this->getContext();

        // remove any non-numeric values from the source config
        if ($this->getConfig('src')) {
            $id = preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src'));

            try {
                $folderMapper = new \Schoolbox\Resource\CategoryMapper();
                $folder       = $folderMapper->findById($id);

                $this->source = $folder;
            } catch (\Exception $e) {
            }
        }
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

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        //file output
        $this->setBlock($templateHandle, 'folders', 'folders_block');
        $this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
        $this->setBlock('folders', 'modify_folder', 'modify_folder_block');


        $treeBuilder = new Folder\TreeBuilder(
            Folder\Mapper::getInstance(),
            TaskMapper::getInstance(),
            UserAccessMapper::getInstance(),
            RoleAccessMapper::getInstance(),
            $_SESSION['user']
        );
        $allFolders = $treeBuilder->buildChildNodes($this->source->getId());

        $this->setVar('folders_block', '');

        $maxFolders = $this->getConfig('size');
        if (count($allFolders) && $this->permission > DENY_ACCESS) {
            $j = 0;

            $this->setVar('folders_block', '');
            $icons = array('homepage'=>'home','folder'=>'folder','course'=>'course');

            foreach ($allFolders as $currentFolder) {
                //set name
                $output = array(
                    'FOLDER_ID'   => $currentFolder['id'],
                    'FOLDER_NAME' => Scrub::text($currentFolder['text']),
                    'FOLDER_ICON' => $icons[$currentFolder['type']],
                    'ON_OFF'      => ++$j % 2 ? '' : 'dark',
                );
                if ($maxFolders > 0 && $j > $maxFolders) {
                    break;
                }

                $this->setVar($output);

                if ($this->permission >= WRITE_ACCESS) {
                    $this->parse('modify_folder_block', 'modify_folder');
                } else {
                    $this->setVar('modify_folder_block', '');
                }

                $this->parse('folders_block', 'folders', true);
            }
        } else {
            $this->setVar('folders_block', '');
        }

        $this->setvar(
            array(
                'COMPONENT_NAME'            => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName(),
                'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getName(),
                'COMPONENT_HANDLE'          => strtolower($templateHandle)
            )
        );

        //setup block for submission button
        $this->setVar('FOLDER_CAT_ID', $this->source->getId());

        $this->setBlock($templateHandle, "submit_folder", "submit_folder_block");

        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_folder_block", "");
        } else {
            $this->parse("submit_folder_block", "submit_folder");
        }

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set category mapper
     *
     * @param null $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return null
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
