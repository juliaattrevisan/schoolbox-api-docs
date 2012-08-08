<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FileListController extends Component_Homepage_ControllerAbstract {


    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

    /**
     * @var Link
     */
    private $fileHandler;

    /**
     * @var AllowedFile
     */
    private $allowedFileHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $this->catHandler = new Category();
        $this->fileHandler = new File();
        $this->allowedFileHandler = new AllowedFile();

        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                $this->getConfig('src') :
                $this->getContext('id')
        );
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'fileList')) {
            return false;
        }
        $this->parseConfig();

        /* Set up component name
        */
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Files',

            // These are for the Select Alternate Source resourcebrowser
            'COMPONENT_CONFIG_SRC_ID'   => $this->source['id'],
            'COMPONENT_CONFIG_SRC_NAME' => $this->source['id'] == $this->getContext('id') ? 'Local' : $this->source['name'],

            'FILES_CATEGORY_ID' => $this->source['id'],
        ));



        //file output
        $this->setBlock($templateHandle, 'files', 'files_block');
        $this->setBlock('files', 'file_url', 'file_url_block');
        $this->setBlock('files', 'file_no_url', 'file_no_url_block');
        $this->setBlock('files', 'modify_file', 'modify_file_block');

        $fileList = $this->fileHandler->getAllInCategory($this->source['id'], array('size' => $this->getConfig('size')));

        $this->setVar('files_block', '');

        if (is_array($fileList)) {
            $j = 0;
            foreach ($fileList as $file) {
                $date = new Alaress_Date_Format($file['submitted'], 'U');

                $output = array('FILE_DESCRIPTION'	=> Scrub::text($file['description']),
                    'FILE_ID'			=> $file['id'],
                    'FILE_MIME_IMAGE'	=> $this->allowedFileHandler->getClass($file["iconFile"]),
                    'FILE_POSTED_BY'	=> $this->user->formatName($file),
                    'FILE_POSTED_BY_ID'	=> $file['owner'],
                    'FILE_POSTED_DATE'	=> $date->getShort(),
                    'FILE_COMMENT'		=> nl2br(Scrub::text($file['comment'])),
                    'ON_OFF'			=> ++$j % 2 ? '' : 'dark',
                    'HIDDEN'            => $file['hidden'] ? 'hidden' : '',
                );
                $this->setVar($output);

                //set name
                if($file['realname'] != "") {

                    $this->setVar('FILE_URL', '/send.php?id='.$file['id']);
                    $this->setVar('FILE_TARGET', '_blank');

                    $this->setVar('file_no_url_block', '');
                    $this->parse('file_url_block', 'file_url');
                }
                else {
                    $this->setVar('file_url_block', '');
                    $this->parse('file_no_url_block', 'file_no_url');
                }

                if($this->permission >= WRITE_ACCESS)
                    $this->parse('modify_file_block', 'modify_file');
                else
                    $this->setVar('modify_file_block','');

                $this->parse('files_block', 'files', true);

            }
        }
        else {
            $this->setVar('files_block', '');
        }

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_file", "submit_file_block");
        if ($this->permission < WRITE_ACCESS)
            $this->setVar("submit_file_block", "");
        else
            $this->parse("submit_file_block", "submit_file");


        return true;

    }


}
