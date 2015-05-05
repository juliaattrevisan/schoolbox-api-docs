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

use Schoolbox\Resource\Folder\Model as CategoryModel;
use Schoolbox\Collection;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Browser\Tree\Node;
use Schoolbox\Resource\Browser\Tree\Node\Icon;
use Schoolbox\User;
use Schoolbox\Resource\File\Mapper as FileMapper;

class Contents
{

    /**
     * An instance of CategoryModel
     *
     * @var CategoryModel
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
     * An instance of \Schoolbox\Resource\LinkMapper
     *
     * @var \Schoolbox\Resource\LinkMapper
     */
    private $linkMapper = null;


    /**
     * An instance of FileMapper
     *
     * @var FileMapper
     */
    private $fileMapper = null;

    /**
     * An instance of group handler
     *
     * @var \Group
     */
    private $groupHandler = null;





    /**
     * Constructor
     *
     * @param CategoryModel $category
     * @param array $filterOptions
     */
    public function __construct(CategoryModel $category, $filterOptions = null)
    {

        /* Mappers
         */
        $this->setLinkMapper(new LinkMapper());
        $this->setFileMapper(new FileMapper());


        /* Other
         */
        $this->setGroupHandler(new \Group());
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
    private function build()
    {

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
    private function buildLinks()
    {
        $allLinkNodes = $this->getLinkMapper()->findAllInCategory($this->getCategory(), array());

        /* Process links
         */
        foreach ($allLinkNodes as $currentLinkNode) {
            /** @var \Schoolbox\Resource\Link $currentLinkNode */
            $node = new Node();
            $node->setNodeId($currentLinkNode->getId());
            $node->setUser($currentLinkNode->getOwner());
            $node->setType('link');
            $icon = new Icon();
            $icon->setFile('html.png');
            $icon->setCss('link');
            $node->setIcon($icon);
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
    private function buildFiles()
    {
        $allFileNodes = $this->getFileMapper()->findForFolders(array($this->getCategory()->getId()), $this->getFilterOptions());

        /* Process files
         */
        foreach ($allFileNodes as $currentFileNode) {

            /* css for the node
             */
            $class = array();

            // write access
            if ($this->getPermission() >= WRITE_ACCESS) {
                $class[] = "write";
            }

            // is hidden
            if ($currentFileNode->isHidden()) {
                $class[] = "hidden";
            }

            // icon
            $class[] = $currentFileNode->getMimetype()->getIconClass() ? $currentFileNode->getMimetype()->getIconClass() : "binary";


            /* Detect which icon to display
             * $allowedFiles->getClass() returns empty string for iconFile 'html_alt1'
             */
            $icon = new Icon();

            if (!empty($currentFileNode->getMimetype()->getIconFile())) {
                $icon->setCss($currentFileNode->getMimetype()->getIconClass());
                $icon->setFile($currentFileNode->getMimetype()->getIconFile());
            }

            $node = new Node();
            $node->setNodeId($currentFileNode->getId());
            $node->setUser($currentFileNode->getOwner());
            $node->setType('file');
            $node->setIcon($icon);
            $node->setName(\Scrub::text($currentFileNode->getTitle()));
            $node->setSize($currentFileNode->getSize() > 0 ? \Alaress_Byte_Format::bytesToString($currentFileNode->getSize()) : "--");
            $node->setCategory($this->getCategory());
            $node->setCss(implode(' ', $class));
            $node->setModifiedAt($currentFileNode->getModifiedAt());

            $this->getFileNodes()->add($node);
        }
    }

    /* Getters/Setters
     */

    /**
     * Set category
     *
     * @param CategoryModel $category
     */
    private function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Get category
     *
     * @return CategoryModel
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
     * @return \Schoolbox\Collection|Node[]
     */
    public function getAllNodes()
    {
        return $this->allNodes;
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
     * @return FileMapper
     */
    public function getFileMapper()
    {
        return $this->fileMapper;
    }

    /**
     * @param FileMapper $fileMapper
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
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
