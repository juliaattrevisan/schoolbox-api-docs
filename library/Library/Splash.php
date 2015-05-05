<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Library_Splash {

    private $templateFile;
    private $categoryID;
    private $vars = array();

    /** @var Schoolbox\Resource\File\Mapper */
    private $fileMapper;

    /**
     * A textbox mapper
     *
     * @var \Schoolbox\Textbox\Mapper
     */
    private $textboxMapper;

    public function __construct($templateFile, $categoryID) {
        $this->setTemplateFile($templateFile);
        $this->setCategoryID($categoryID);
        $this->setFileMapper(Schoolbox\Resource\File\Mapper::getInstance());
        $this->setTextboxMapper(new \Schoolbox\Textbox\Mapper());
    }

    public function setVar($token, $textboxID) {
        $this->vars[$token] = $textboxID;
    }

    public function setVars($vars) {
        $this->vars = $vars;
    }

    public function render(SitePage $page) {
        $page->setFile(TEMPLATE_content, "modules/library/{$this->getTemplateFile()}.html");
        $page->setBlock(TEMPLATE_content, 'image', 'image_block');

        foreach($this->vars as $token => $textID) {
            $box = $this->getTextboxMapper()->findById($textID);
            $page->setVar($token, Scrub::html($box->getBody()));
        }

        $allFiles = $this->getFileMapper()->findForFolders([ $this->getCategoryID() ], array('filter' => array('image')));
        if(is_array($allFiles)) {
            foreach ($allFiles as $currentFile) {
                $page->setVar(array(
                    'IMAGE_ID' => $currentFile['id'],
                    'IMAGE_NAME' => $currentFile['description'],
                ));
                $page->parse('image_block', 'image', true);
            }
        }
        else {
            $page->setVar('image_block','');
        }
    }


    /* GETTERS AND SETTERS */
    private function setTemplateFile($file) {
        $this->templateFile = $file;
    }

    public function getTemplateFile() {
        return $this->templateFile;
    }

    public function setCategoryID($id) {
        $this->categoryID = $id;
    }

    public function getCategoryID() {
        return $this->categoryID;
    }


    /**
     * @return \Schoolbox\Resource\File\Mapper
     */
    public function getFileMapper()
    {
        return $this->fileMapper;
    }


    /**
     * @param \Schoolbox\Resource\File\Mapper $fileMapper
     * @return static
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * Set textbox mapper
     *
     * @param \Schoolbox\Textbox\Mapper $textboxMapper
     */
    private function setTextboxMapper($textboxMapper)
    {
        $this->textboxMapper = $textboxMapper;
    }

    /**
     * Return textbox mapper
     *
     * @return \Schoolbox\Textbox\Mapper
     */
    private function getTextboxMapper()
    {
        return $this->textboxMapper;
    }
}
