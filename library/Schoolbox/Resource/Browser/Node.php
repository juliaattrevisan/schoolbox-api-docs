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

namespace Schoolbox\Resource\Browser;


use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Exception;

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
     */
    protected function processNode($mimeFilter = null)
    {
        $output = array(
            'breadcrumbs' => $this->getBreadcrumbs(),
            'records'     => $this->getCategories(),
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


        return array_reverse($breadcrumbs);
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
     * @param null $categoryMapper
     */
    protected function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }


    /**
     * Get category mapper
     *
     * @return CategoryMapper
     */
    protected function getCategoryMapper()
    {
        return $this->categoryMapper;
    }


    /**
     * @param \Group $groupHandler
     * @return self
     */
    protected function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
        return $this;
    }


    /**
     * @return \Group
     */
    protected function getGroupHandler()
    {
        return $this->groupHandler;
    }


}