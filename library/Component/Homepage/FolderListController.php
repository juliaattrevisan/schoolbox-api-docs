<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FolderListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var array
     */
    private $source;

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        $this->groupHandler = new Group();
        $this->catHandler = new Category();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'folderList')) {
            return false;
        }
        $this->parseConfig();

	    if($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
		    return false;
	    }

        //file output
        $this->setBlock($templateHandle, 'folders', 'folders_block');
        $this->setBlock('folders', 'homepage_folder', 'homepage_folder_block');
        $this->setBlock('folders', 'modify_folder', 'modify_folder_block');


        $folderList = $this->catHandler->getCategoryList($this->source['id'], $this->getConfig());

        $this->setVar('folders_block', '');

        if (is_array($folderList)) {
            $flag = 0;
            $j = 0;
            foreach ($folderList as $folder) {
                if($this->groupHandler->getPermission($folder['id']) > DENY_ACCESS)
                {
                    //set name
                    $output = array('FOLDER_ID'		=> $folder['id'],
                        'FOLDER_NAME'	=> Scrub::text($folder['name']),
                        'FOLDER_ICON'	=> $folder['template']?'house':'folder_blue_full',
                        'ON_OFF'		=> ++$j % 2 ? '' : 'dark',
                    );
                    $this->setVar($output);

                    if($this->permission >= WRITE_ACCESS)
                        $this->parse('modify_folder_block', 'modify_folder');
                    else
                        $this->setVar('modify_folder_block','');

                    $this->parse('folders_block', 'folders', true);
                    $flag = 1;

                }
            }
            if(!$flag) { $this->setVar('folders_block', '');}
        } else
            $this->setVar('folders_block', '');

        $this->setvar(array(
            'COMPONENT_NAME'            => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Folders',
            'COMPONENT_CONFIG_SRC_ID'   => $this->source['id'],
            'COMPONENT_CONFIG_SRC_NAME' => $this->source['name'],
            'COMPONENT_HANDLE'          => strtolower($templateHandle)
        ));

        //setup block for submission button
        $this->setVar('FOLDER_CAT_ID', $this->source['id']);
        $this->setBlock($templateHandle, "submit_folder", "submit_folder_block");
        if ($this->permission < WRITE_ACCESS)
            $this->setVar("submit_folder_block", "");
        else
            $this->parse("submit_folder_block", "submit_folder");


        return true;
    }

}
