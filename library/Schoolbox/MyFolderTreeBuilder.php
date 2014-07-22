<?php
/**
 * Tree builder for folders in the My Files section.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\Mapper;

class MyFolderTreeBuilder {

    /**
     * @var MyFolderMapper
     */
    protected $mapper;

    /**
     * @var User
     */
    protected $user;

    /**
     * @param MyFolderMapper $mapper
     * @param User           $user
     */
    public function __construct(MyFolderMapper $mapper, User $user) {
        $this->mapper = $mapper;
        $this->user   = $user;
    }

    /**
     * Builds a list of child nodes for $categoryId
     *
     * @param  mixed $categoryId
     * @return array
     */
    public function buildChildNodes($categoryId) {
        $output = array();
        $search = array(
            'owner'  => $this->user->getId(),
            'parent' => $categoryId,
        );

        // Did I ask for the root node? get it
        if ($categoryId === '#') {
            $childSearch = $search;
            $childSearch['parent'] = 0;
            $output[] = array(
                'id'       => '0',
                'parent'   => '#',
                'text'     => 'Resources',
                'children' => $this->mapper->countByColumnList($childSearch) > 0,
                'type'     => 'root',
                'li_attr'  => array(
                    'data-permission' => 'full',
                ),
            );
        }
        else {
            // Child nodes
            foreach ($this->mapper->findByColumnList($search) as $folder) {
                /** @var MyFolder $folder */

                // Type: homepage or folder
                // Could be a course as well, if any child has tasks.
                $childSearch = $search;
                $childSearch['parent'] = $folder->getId();

                // Output something.
                $output[] = array(
                    'id'       => $folder->getId(),
                    'parent'   => $folder->getParent()->getId() ?: '0',
                    'text'     => $folder->getName(),
                    'children' => $this->mapper->countByColumnList($childSearch) > 0,
                    'type'     => 'folder',
                    'li_attr'  => array(
                        'data-permission' => 'full',
                    ),
                );
            }
        }

        return $output;
    }

    /**
     * Builds a partial tree.
     *
     * This method loads $categoryId's children, then its parent's direct
     * children, and repeats all the way up to the parent node.
     *
     * @param  mixed $categoryId
     * @return array
     */
    public function buildPartialTree($categoryId) {

        // build a list of parentage
        $parentList[] = $parentId = $categoryId;
        $i = 0;
        while ($parentId > 0) {
            if ($i++ > 100) {
                break;
            }
            try {
                $parentList[] = $parentId = $this->mapper->findById($parentId)->getParent()->getId();
            }
            catch (\Schoolbox\Mapper\Exception $e) {
                break;
            }
        }

        // Build the root node
        $partialTree = $this->buildChildNodes('#');

        // Build the parent nodes
        foreach ($parentList as $parentCategoryId) {
            $partialTree = array_merge(
                $partialTree,
                $this->buildChildNodes($parentCategoryId)
            );
        }

        // Open $categoryId's node and its parents
        foreach ($partialTree as &$leaf) {
            $leaf['state']['opened']   = in_array($leaf['id'], $parentList);
            $leaf['state']['selected'] = ($leaf['id'] == $categoryId);
        }
        unset($leaf);

        return $partialTree;
    }

}