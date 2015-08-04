<?php
/**
 * Evaluation_Question_Category_List
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Evaluation_Question_Category_List
{
    /** @var SitePage */
    private $page;

    /** @var array */
    private $allCategories = array();

    /** @var int */
    private $selectedAssess = 0;

    /** @var int */
    private $selectedCategory = 0;

    /** @var Evaluation_Question_Category */
    private $categoryHandler;


    /**
     * Constructor
     *
     * @param SitePage  $page
     * @param int       $selectedAssess
     * @param int       $selectedCategory
     * @param array     $allCategories
     */
    public function __construct($page, $selectedAssess, $selectedCategory, $allCategories)
    {
        $this->setPage($page);
        $this->setSelectedAssess($selectedAssess);
        $this->setSelectedCategory($selectedCategory);
        $this->setAllCategories($allCategories);
        $this->setCategoryHandler(new Assess_Question_Category());

        $this->setup();
    }

    /**
     * Get a list of nodes that should be open, for the javascript tree
     *
     * @param $category_id
     * @return string
     */
    private function getJsNodePath($category_id)
    {
        $pathArray = array();
        $path = array();

        $allPaths = $this->getPathSegment($category_id, $pathArray);

        /* Accumulate all the little path pieces
           */
        if (is_array($allPaths) && count($allPaths)) {
            foreach ($allPaths as $currentPath) {
                $path[] = '\'node_' . $currentPath['question_category_id'] . '\'';
            }
        }
        // Open the home path too
        $path[] = '\'node_0\'';

        return implode(',', $path);
    }

    /**
     * Find the way back to parent
     *
     * @param $category_id
     * @param $pathArray
     *
     * @return array
     */
    private function getPathSegment($category_id, $pathArray)
    {
        if ($category_id == 0) {
            return $pathArray;
        } else {
            $category = $this->getCategoryHandler()->getByID($category_id);

            $pathArray[] = $category;

            return $this->getPathSegment($category['parent_id'], $pathArray);
        }
    }

    /**
     * Set up blocks and stuff
     */
    private function setup()
    {
        $this->getPage()->setBlock(TEMPLATE_content, 'category_list_start', 'category_list_start_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'category_list_item_start', 'category_list_item_start_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'category_list_item', 'category_list_item_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'category_list_item_end', 'category_list_item_end_block');

        $this->getPage()->parse('category_list_start_block', 'category_list_start', true);

        $this->getPage()->setVar(array(
            'category_list_item_start_block' => '',
            'category_list_item_end_block' => '',
            'question_category_block' => '',
        ));
    }

    /**
     * Render hierarchy wrapper method
     */
    public function render()
    {
        // Shoehorn in a root node
        $rootNode = [
            'question_category_id' => 0,
            'parent_id'            => '#',
            'name'                 => 'Home',
            'availability'         => '',
            'numQuestions'         => 0,
            'children'             => $this->getAllCategories(),
        ];
        $this->build([$rootNode]);

        /* Tell javascript tree which nodes to open.
           */
        $this->getPage()->setVar(array(
            'SELECTED_NODES' => $this->getJsNodePath($this->getSelectedCategory()),
        ));
    }

    /**
     * Recursively construct and render a category hierarchy
     *
     * @param $allCategories
     */
    private function build($allCategories)
    {
        if (is_array($allCategories) && count($allCategories)) {
            foreach ($allCategories as $currentCategory) {

                /* Find out if this the category that has been selected
                     */
                if ($currentCategory['question_category_id'] == $this->getSelectedCategory()) {
                    $selected = 'selected';
                } else {
                    $selected = '';
                }

                $this->getPage()->setVar(array(
                    'CATEGORY_LIST_ITEM_NODE' => 'node_' . $currentCategory['question_category_id'],
                    'CATEGORY_LIST_ITEM_ASSESS_ID' => $this->getSelectedAssess(),
                    'CATEGORY_LIST_ITEM_CATEGORY_ID' => $currentCategory['question_category_id'],
                    'CATEGORY_LIST_ITEM_NAME' => Scrub::text($currentCategory['name']),
                    'CATEGORY_LIST_ITEM_NUMQUESTIONS' => $currentCategory['numQuestions'],
                    'CATEGORY_LIST_ITEM_SELECTED' => $selected,
                ));

                $this->getPage()->parse('category_list_item_block', 'category_list_item', true);

                if (count($currentCategory['children'])) {
                    $this->getPage()->parse('category_list_item_block', 'category_list_item_start', true);
                    $this->build($currentCategory['children']);
                    $this->getPage()->parse('category_list_item_block', 'category_list_item_end', true);
                }
            }
        }
    }

    /* Getters/Setters
      */

    /**
     * Get page
     *
     * @return SitePage
     */
    private function getPage()
    {
        return $this->page;
    }

    /**
     * Set page
     *
     * @param SitePage $page
     */
    private function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Set all categories
     *
     * @param array $allCategories
     */
    private function setAllCategories($allCategories)
    {
        $this->allCategories = $allCategories;
    }

    /**
     * Get all categories
     *
     * @return array
     */
    private function getAllCategories()
    {
        return $this->allCategories;
    }

    /**
     * Set selected category
     *
     * @param int $selectedCategory
     */
    private function setSelectedCategory($selectedCategory)
    {
        $this->selectedCategory = $selectedCategory;
    }

    /**
     * Get selected category
     *
     * @return int
     */
    private function getSelectedCategory()
    {
        return $this->selectedCategory;
    }

    /**
     * Set selected assess
     *
     * @param int $selectedAssess
     */
    private function setSelectedAssess($selectedAssess)
    {
        $this->selectedAssess = $selectedAssess;
    }

    /**
     * Get selected assess
     *
     * @return int
     */
    private function getSelectedAssess()
    {
        return $this->selectedAssess;
    }

    /**
     * Set category handler
     *
     * @param Assess_Question_Category $categoryHandler
     */
    private function setCategoryHandler($categoryHandler)
    {
        $this->categoryHandler = $categoryHandler;
    }

    /**
     * Get category handler
     *
     * @return Assess_Question_Category
     */
    private function getCategoryHandler()
    {
        return $this->categoryHandler;
    }
}
