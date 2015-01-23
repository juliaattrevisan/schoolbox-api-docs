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
use Schoolbox\UserMapper;
use Schoolbox\User;

class Contents {

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
     * An array of options to pass to findAllInCategory() calls
     *
     * @var array
     */
    private $filterOptions = array();

    /**
     * An instance of \Schoolbox\Collection
     *
     * @var \Schoolbox\Collection
     */
    private $linkNodes = null;

    /**
     * An instance of \Schoolbox\Collection
     *
     * @var \Schoolbox\Collection
     */
    private $fileNodes = null;

    /**
     * An instance of \Schoolbox\Collection
     *
     * @var \Schoolbox\Collection
     */
    private $allNodes = null;

    /**
     * An instance of \Schoolbox\UserMapper
     *
     * @var \Schoolbox\UserMapper
     */
    private $userMapper = null;

    /**
     * An instance of \Schoolbox\Resource\LinkMapper
     *
     * @var \Schoolbox\Resource\LinkMapper
     */
    private $linkMapper = null;

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
     * An instance of file handler
     *
     * @var \File
     */
    private $fileHandler = null;

    /**
     * Constructor
     *
     * @param Category $category
     * @param array $filterOptions
     */
    public function __construct(Category $category, $filterOptions = null) {

        /* Handlers
         */
        $this->setGroupHandler(new \Group());
        $this->setAllowedFiles(new \AllowedFile());
        $this->setFileHandler(new \File());

        /* Mappers
         */
        $this->setUserMapper(new UserMapper());
        $this->setLinkMapper(new LinkMapper());

        /* Other
         */
        $this->setCategory($category);
        $this->setPermission($this->getGroupHandler()->getPermission($category->getId()));
        $this->setFilterOptions($filterOptions);

        /* Collections
         */
        $this->setLinkNodes(new Collection());
        $this->setFileNodes(new Collection());
        $this->setAllNodes(new Collection());

        /* Hadoken
         */
        $this->build();
    }

    /**
     * Build a collection of Nodes to render for the opened category
     *
     * @return Collection
     */
    private function build() {

        /* Individual types
         */
        $this->buildLinks();
        $this->buildFiles();

        /* Combined nodes
         */
        $this->getAllNodes()->merge($this->getLinkNodes());
        $this->getAllNodes()->merge($this->getFileNodes());
        $this->getAllNodes()->sortBy('name', 'asc');
    }

    /**
     * A list of links in this category
     *
     */
    private function buildLinks() {
        $allLinkNodes = $this->getLinkMapper()->findAllInCategory($this->getCategory(), array());

        /* Process links
         */
        foreach ($allLinkNodes as $currentLinkNode) {
            $node = new Node();
            $node->setNodeId($currentLinkNode->getId());
            $node->setUser($currentLinkNode->getOwner());
            $node->setType('link');
            $node->setIcon('link');
            $node->setName(\Scrub::text($currentLinkNode->getDescription()));
            $node->setCategory($this->getCategory());
            $node->setCss(implode(' ', array('link')));
            $node->setModifiedAt($currentLinkNode->getSubmitted());

            $this->getLinkNodes()->add($node);
        }
    }

    /**
     * A list of files in this category
     */
    private function buildFiles() {
       $allFileNodes = $this->getFileHandler()->getAllInCategory($this->getCategory()->getId(), $this->getFilterOptions());

        /* Process files
         */
        if (is_array($allFileNodes) && count($allFileNodes)) {
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

                try {
                    $user = $this->getUserMapper()->findById($currentFileNode['owner']);
                }
                catch (\Exception $e) {
                    $user = new User();
                    $user->setId($currentFileNode['owner']);
                }

                $node = new Node();
                $node->setNodeId($currentFileNode['id']);
                $node->setUser($user);
                $node->setType('file');
                $node->setIcon($fileListType);
                $node->setName(\Scrub::text($currentFileNode['description']));
                $node->setSize($currentFileNode['file_size'] > 0 ? \Alaress_Byte_Format::bytesToString($currentFileNode['file_size']) : "--");
                $node->setCategory($this->getCategory());
                $node->setCss(implode(' ' , $class));
                $node->setModifiedAt(new \Alaress_Date_Relative($currentFileNode['modified']));

                $this->getFileNodes()->add($node);
            }
        }
    }

    /* Getters/Setters
     */

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

    /**
     * Set filter options
     *
     * @param array $filterOptions
     */
    private function setFilterOptions($filterOptions)
    {
        $this->filterOptions = $filterOptions;
    }

    /**
     * Get filter options
     *
     * @return array
     */
    private function getFilterOptions()
    {
        return $this->filterOptions;
    }

    /**
     * Set user mapper
     *
     * @param \Schoolbox\UserMapper $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Get user mapper
     *
     * @return \Schoolbox\UserMapper
     */
    private function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * Set all nodes
     *
     * @param \Schoolbox\Collection $allNodes
     */
    private function setAllNodes($allNodes)
    {
        $this->allNodes = $allNodes;
    }

    /**
     * Get all nodes
     *
     * @return \Schoolbox\Collection
     */
    public function getAllNodes()
    {
        return $this->allNodes;
    }

    /**
     * Set file handler
     *
     * @param \File $fileHandler
     */
    private function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
    }

    /**
     * Get file handler
     *
     * @return \File
     */
    private function getFileHandler()
    {
        return $this->fileHandler;
    }

    /**
     * Set link mapper
     *
     * @param \Schoolbox\Resource\LinkMapper $linkMapper
     */
    private function setLinkMapper($linkMapper)
    {
        $this->linkMapper = $linkMapper;
    }

    /**
     * Get link mapper
     *
     * @return \Schoolbox\Resource\LinkMapper
     */
    private function getLinkMapper()
    {
        return $this->linkMapper;
    }

    /**
     * @param \Schoolbox\Collection $fileNodes
     */
    private function setFileNodes($fileNodes)
    {
        $this->fileNodes = $fileNodes;
    }

    /**
     * Get file nodes
     *
     * @return \Schoolbox\Collection
     */
    public function getFileNodes()
    {
        return $this->fileNodes;
    }

    /**
     * @param \Schoolbox\Collection $linkNodes
     */
    private function setLinkNodes($linkNodes)
    {
        $this->linkNodes = $linkNodes;
    }

    /**
     * Get link nodes
     *
     * @return \Schoolbox\Collection
     */
    public function getLinkNodes()
    {
        return $this->linkNodes;
    }
}