<?php
/**
 * Contents
 *
 * Display a hybrid view of files and links for a particular $category
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Tree;

use Schoolbox\Resource\Category;
use Schoolbox\Collection;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Browser\Tree\Node;

class Contents {

    /**
     * An instance of group handler
     *
     * @var \Group
     */
    private $groupHandler = null;

    /**
     * An instance of allowed files handler
     *
     * @var \AllowedFile
     */
    private $allowedFiles = null;

    /**
     * An instance of \Schoolbox\Resource\Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $category = null;

    /**
     * Permission value
     * 
     * @var int
     */
    private $permission = 0;

    /**
     * Constructor
     *
     * @param Category $category
     */
    public function __construct(Category $category) {
        $this->setGroupHandler(new \Group());
        $this->setAllowedFiles(new \AllowedFile());

        $this->setCategory($category);
        $this->setPermission($this->getGroupHandler()->getPermission($category->getId()));
    }

    /**
     * Build a collection of Nodes to render for the opened category
     *
     * @return Collection
     */
    public function build() {
        $fileHandler = new \File();

        /* Adapt and overcome
         */
        $allNodes = new Collection();

        /* Links
         */
        $linkMapper = new LinkMapper();
        $allLinkNodes = $linkMapper->findAllInCategory($this->getCategory(), array());

        /* Process links
         */
        foreach ($allLinkNodes as $currentLinkNode) {
            $node = new Node();
            $node->setNodeId($currentLinkNode->getId());
            $node->setType('link');
            $node->setIcon('link');
            $node->setName(\Scrub::text($currentLinkNode->getDescription()));
            $node->setCategory($this->getCategory());
            $node->setCss(implode(' ', array('link')));
            $node->setModifiedAt($currentLinkNode->getSubmitted());

            $allNodes->add($node);
        }

        /* Files
         */
        $allFileNodes = new Collection($fileHandler->getAllInCategory($this->getCategory()->getId()));

        /* Process files
         */
        foreach ($allFileNodes as $currentFileNode) {

            // if the file is associated with due work, it shouldn't be visible here
            if ($currentFileNode['type'] || $currentFileNode['typeName']) {
                continue;
            }

            /* css for the node
             */
            $class = array();

            // write access
            if ($this->getPermission() >= WRITE_ACCESS) {
                $class[] = "write";
            }

            // is hidden
            if ($currentFileNode['hidden'] > 0) {
                $class[] = "hidden";
            }

            // icon
            $class[] = !empty($currentFileNode['iconFile']) ? $this->getAllowedFiles()->getClass($currentFileNode["iconFile"]) : "binary";

            /* Detect which icon to display
             * $allowedFiles->getClass() returns empty string for iconFile 'html_alt1'
             */
            if (!empty($currentFileNode['iconFile'])) {
                $fileListType = $this->getAllowedFiles()->getClass($currentFileNode["iconFile"]);
            }
            else {
                $fileListType = "textfile";
            }

            $node = new Node();
            $node->setNodeId($currentFileNode['id']);
            $node->setType('file');
            $node->setIcon($fileListType);
            $node->setName(\Scrub::text($currentFileNode['description']));
            $node->setSize($currentFileNode['file_size'] > 0 ? \Alaress_Byte_Format::bytesToString($currentFileNode['file_size']) : "--");
            $node->setCategory($this->getCategory());
            $node->setCss(implode(' ' , $class));
            $node->setModifiedAt(new \Alaress_Date_Relative($currentFileNode['modified']));

            $allNodes->add($node);
        }

        return $allNodes;
    }

    /**
     * Set allowed files
     *
     * @param \AllowedFile $allowedFiles
     */
    private function setAllowedFiles($allowedFiles)
    {
        $this->allowedFiles = $allowedFiles;
    }

    /**
     * Get allowed files
     *
     * @return \AllowedFile
     */
    private function getAllowedFiles()
    {
        return $this->allowedFiles;
    }

    /**
     * Set category
     *
     * @param \Schoolbox\Resource\Category $category
     */
    private function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Get category
     *
     * @return \Schoolbox\Resource\Category
     */
    private function getCategory()
    {
        return $this->category;
    }

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set permission
     *
     * @param int $permission
     */
    private function setPermission($permission)
    {
        $this->permission = $permission;
    }

    /**
     * Get permission
     *
     * @return int
     */
    private function getPermission()
    {
        return $this->permission;
    }
    
    
} 