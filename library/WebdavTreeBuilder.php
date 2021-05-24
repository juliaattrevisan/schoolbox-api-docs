<?php

/**
 * Tree builder for folders in the Network Drives (WebDAV) section.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

class WebdavTreeBuilder
{

    /**
     * @var PortfolioWebDAV
     */
    protected $connection;

    /**
     * @param PortfolioWebDAV $connection
     */
    public function __construct(PortfolioWebDAV $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Builds a list of child nodes for $folderId
     *
     * @param  mixed $folderId
     * @return array
     */
    public function buildChildNodes($folderId)
    {
        $output = array();

        // Did I ask for the root node? get it
        if ($folderId === '#') {
            $output[] = array(
                'id'       => 0,
                'parent'   => '#',
                'text'     => 'Top',
                'children' => $this->connection->hasChildren(0),
                'type'     => 'root',
                'li_attr'  => array(
                    'data-permission' => 'write',
                ),
            );
        } else {
            foreach ($this->connection->getFolderList($folderId) as $folder) {
                $output[] = array(
                    'id'       => intval($folder['id']),
                    'text'     => $folder['description'],
                    'parent'   => intval($folder['parent']),
                    'children' => $this->connection->hasChildren($folder['id']),
                    'type'     => 'folder',
                    'li_attr'  => array(
                        'data-permission' => 'write',
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
     * @param  mixed $folderId
     * @return array
     */
    public function buildPartialTree($folderId)
    {

        // build a list of parentage
        $parentList[] = $parentId = $folderId;
        $i = 0;
        while ($parentId > 0) {
            if ($i++ > 100) {
                break;
            }
            $parentList[] = $parentId = $this->connection->getParent($parentId);
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
            $leaf['state']['selected'] = ($leaf['id'] == $folderId);
        }
        unset($leaf);

        return $partialTree;
    }
}
