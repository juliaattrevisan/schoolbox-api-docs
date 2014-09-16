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


use Schoolbox\File\Exception;

class Node {

	/**
	 * A node id, usually a primary key
	 *
	 * @var int
	 */
	private $nodeId = 0;

	/**
	 * Resultant node information
	 *
	 * @var array
	 */
	private $result = array();

	/**
	 * An instance of a category mapper
	 *
	 * @var \Schoolbox\Resource\CategoryMapper
	 */
	private $categoryMapper = null;

    /**
     * Old school group handler reference
     * @var \Group
     */
    private $groupHandler;

    /**
	 * Constructor
	 *
	 * $nodeId is the primary key of the category whose contents
	 * you wish to present
	 *
	 * @param $nodeId
	 */
	public function __construct($nodeId) {
		if (is_numeric($nodeId)) {
			$this->setNodeId($nodeId);
			$this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());
            $this->setGroupHandler(new \Group());

            // Check we have permission to render node
            if( $nodeId > 0 && $this->getGroupHandler()->getPermission($nodeId) < READ_ACCESS) {
                throw new \Schoolbox\Resource\Exception('Permission denied');
            }


			$this->processNode();
		}
	}

	/**
	 * Render it out in a json format in case you need it
	 */
	public function ajax() {
		header("Content-type: application/json");
		echo json_encode($this->getResult());
	}

	/**
	 * Render it out so phplib can do it
	 *
	 * @return array
	 */
	public function render() {
		return $this->getResult();
	}

	/**
	 * Combine the categories and files into a single list
	 */
	private function processNode() {
		$output = array(
			'breadcrumbs' => array_reverse($this->getBreadcrumbs()),
			'records' => array_merge($this->getCategories(), $this->getFiles()),
		);

		$this->setResult($output);
	}

	/**
	 * Get a list of breadcrumbs for this node
	 *
	 * @return array
	 */
	private function getBreadcrumbs() {
		$breadcrumbs = array();

		/* Special case for root node
		 */
		if ($this->getNodeId()) {
			$category = $this->getCategoryMapper()->findById($this->getNodeId());

			$breadcrumbs[] = array(
				'categoryId' => $category->getId(),
				'name' => $category->getName(),
			);

			for ($i = 0; ($category->getParent()->getId() > 0) && ($i < 100); $i++) {
                if( $this->getGroupHandler()->getPermission($category->getParent()->getId()) >= READ_ACCESS) {
                    $currentCategory = $this->getCategoryMapper()->findById($category->getParent()->getId());

                    $breadcrumbs[] = array(
                        'categoryId' => $currentCategory->getId(),
                        'name' => $currentCategory->getName(),
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
	private function getCategories() {
		$output = array();

		$allCategories = $this->getCategoryMapper()->findByParent($this->getNodeId());

		foreach($allCategories as $currentCategory) {
            if( $this->getGroupHandler()->getPermission($currentCategory->getId()) >= READ_ACCESS) {
                    $record = array(
                    'type' => 'category',
                    'categoryId' => $currentCategory->getId(),
                    'name' => $currentCategory->getName()
                );

                $output[] = $record;
            }
		}

		return $output;
	}

	/**
	 * Get all the files for a particular category
	 *
	 * @return array
	 */
	private function getFiles() {
		$output = array();

		$fileMapper = new \Schoolbox\Resource\FileMapper();
		$mimetypeMapper = new \Schoolbox\File\MimetypeMapper();

        //Double check perms, should not get here but just incase
        if( $this->getGroupHandler()->getPermission($this->getNodeId()) < READ_ACCESS) {
            return $output;
        }

		$allFiles = $fileMapper->findByColumn('category', $this->getNodeId(), false);

		foreach($allFiles as $currentFile) {

			/* Use a generic default icon for unknown filetypes that
 			 * could accumulate in the schoolbox
 			 */
			try {
				$mimetype = $mimetypeMapper->getByFilename($currentFile->getFilename());
			}
			catch (\Exception $e) {
				$mimetype = $mimetypeMapper->getUnknownMime();
			}

			/* If the mimetype table is empty, it'll spawn with a dunce object
			 * SB-3911
			 */
			if (!is_numeric($mimetype->getFileTypeID())) {
				$mimetype = $mimetypeMapper->getUnknownMime();
			}

			$record = array(
				'type' => 'file',
				'fileId' => $currentFile->getId(),
				'name' => $currentFile->getDescription(),
				'icon' => $mimetype->getIconFile(),
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
	private function setNodeId($nodeId) {
		$this->nodeId = $nodeId;
	}

	/**
	 * Get node id
	 *
	 * @return int
	 */
	private function getNodeId() {
		return $this->nodeId;
	}

	/**
	 * Set result
	 *
	 * @param array $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * Get result
	 *
	 * @return array
	 */
	private function getResult() {
		return $this->result;
	}

	/**
	 * Set category mapper
	 *
	 * @param \Schoolbox\Resource\Category $categoryMapper
	 */
	private function setCategoryMapper($categoryMapper) {
		$this->categoryMapper = $categoryMapper;
	}

	/**
	 * Get category mapper
	 *
	 * @return \Schoolbox\Resource\Category
	 */
	private function getCategoryMapper() {
		return $this->categoryMapper;
	}

    /**
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }



}