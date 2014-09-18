<?php
/**
 * Node
 *
 * Render a list of categories and files which sit inside
 * a particular category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Browser;

use Schoolbox\Collection;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\File;
use Schoolbox\Resource\FileMapper;

class Node
{

    /**
     * A node id, usually a primary key
     *
     * @var int
     */
    protected $nodeId = 0;

    /**
     * Resultant node information
     *
     * @var array
     */
    protected $result = array();

    /**
     * An instance of a category mapper
     *
     * @var CategoryMapper
     */
    protected $categoryMapper = null;

    /**
     * Old school group handler reference
     * @var \Group
     */
    protected $groupHandler;

    /**
     * Constructor
     *
     * $nodeId is the primary key of the category whose contents
     * you wish to present
     *
     * @param int $nodeId
     * @param string $mimeFilter
     * @throws Exception
     */
    public function __construct($nodeId, $mimeFilter = null)
    {
        if (is_numeric($nodeId)) {
            $this->setNodeId($nodeId);
            $this->setCategoryMapper(new CategoryMapper());
            $this->setGroupHandler(new \Group());

            // Check we have permission to render node
            if ($nodeId > 0 && $this->getGroupHandler()->getPermission($nodeId) < READ_ACCESS) {
                throw new Exception('Permission denied');
            }


            $this->processNode($mimeFilter);
        }
    }

    /**
     * Render it out in a json format in case you need it
     */
    public function ajax()
    {
        header("Content-type: application/json");
        echo json_encode($this->getResult());
    }

    /**
     * Render it out so phplib can do it
     *
     * @return array
     */
    public function render()
    {
        return $this->getResult();
    }

    /**
     * Combine the categories and files into a single list
     *
     * @param string $mimeFilter
     */
    protected function processNode($mimeFilter = null)
    {
        $output = array(
            'breadcrumbs' => array_reverse($this->getBreadcrumbs()),
            'records'     => array_merge($this->getCategories(), $this->getFiles($mimeFilter)),
        );

        $this->setResult($output);
    }

    /**
     * Get a list of breadcrumbs for this node
     *
     * @return array
     */
    protected function getBreadcrumbs()
    {
        $breadcrumbs = array();

        /* Special case for root node
         */
        if ($this->getNodeId()) {
            $category = $this->getCategoryMapper()->findById($this->getNodeId());

            $breadcrumbs[] = array(
                'categoryId' => $category->getId(),
                'name'       => $category->getName(),
            );

            for ($i = 0; ($category->getParent()->getId() > 0) && ($i < 100); $i++) {
                if ($this->getGroupHandler()->getPermission($category->getParent()->getId()) >= READ_ACCESS) {
                    $currentCategory = $this->getCategoryMapper()->findById($category->getParent()->getId());

                    $breadcrumbs[] = array(
                        'categoryId' => $currentCategory->getId(),
                        'name'       => $currentCategory->getName(),
                    );

                    $category = $currentCategory;
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get all the subcategories for a particular category
     *
     * @return array
     */
    protected function getCategories()
    {
        $output = array();

        $allCategories = $this->getCategoryMapper()->findByParent($this->getNodeId());

        foreach ($allCategories as $currentCategory) {
            if ($this->getGroupHandler()->getPermission($currentCategory->getId()) >= READ_ACCESS) {
                $record = array(
                    'type'       => 'category',
                    'categoryId' => $currentCategory->getId(),
                    'name'       => $currentCategory->getName()
                );

                $output[] = $record;
            }
        }

        return $output;
    }

    /**
     * Get all the files for a particular category
     *
     * @param string $mimeFilter
     * @return array
     */
    protected function getFiles($mimeFilter = null)
    {
        $output = array();

        $fileMapper = new FileMapper();
        $mimetypeMapper = new MimetypeMapper();

        //Double check perms, should not get here but just incase
        if ($this->getGroupHandler()->getPermission($this->getNodeId()) < READ_ACCESS) {
            return $output;
        }

        $options = array();

        if ($mimeFilter) {
            $options['mime'] = $mimeFilter;
        }

        /** @var Collection|File[] $allFiles */
        $allFiles = $fileMapper->findByColumn('category', $this->getNodeId(), $options);

        foreach ($allFiles as $currentFile) {

            /* Use a generic default icon for unknown filetypes that
              * could accumulate in the schoolbox
              */
            try {
                $mimetype = $mimetypeMapper->getByFilename($currentFile->getFilename());
            } catch (\Exception $e) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            /* If the mimetype table is empty, it'll spawn with a dunce object
             * SB-3911
             */
            if (!is_numeric($mimetype->getFileTypeID())) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            $record = array(
                'type'   => 'file',
                'fileId' => $currentFile->getId(),
                'name'   => $currentFile->getDescription(),
                'mime'   => $mimetype->getMime(),
                'icon'   => $mimetype->getIconFile(),
                'size'   => $currentFile->getFilesize(),
            );

            $output[] = $record;
        }

        return $output;
    }

    /* Getters/Setters
     */

    /**
     * Set node id
     *
     * @param int $nodeId
     */
    protected function setNodeId($nodeId)
    {
        $this->nodeId = $nodeId;
    }

    /**
     * Get node id
     *
     * @return int
     */
    protected function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * Set result
     *
     * @param array $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return array
     */
    protected function getResult()
    {
        return $this->result;
    }

    /**
     * Set category mapper
     *
     * @param \Schoolbox\Resource\CategoryMapper $categoryMapper
     */
    protected function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return \Schoolbox\Resource\CategoryMapper
     */
    protected function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param \Group $groupHandler
     */
    protected function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return \Group
     */
    protected function getGroupHandler()
    {
        return $this->groupHandler;
    }


}