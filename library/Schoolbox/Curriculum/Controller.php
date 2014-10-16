<?php
/**
 * Controller for the base of the curriculum module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node;

class Controller extends Base
{
    /**
     * @var Node\Mapper
     */
    protected $nodeMapper;

    public function browse()
    {
        // Fetch all the nodes at once, and parse into a jstree like status
        $tree = array();
        foreach ($this->getNodeMapper()->findAll() as $node) {
            /** @var Node\Model $node */
            $tree[] = $this->getAsJsTree($node);
        }

        // Load parent nodes and all of their subnodes, and dump them in a tree
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum',
                'template' => '@curriculum/browse.twig',
                'data'     => array(
                    'tree' => $tree,
                ),
            )
        );
    }

    /**
     * Helper method for fetching a node as a JsTree entry.
     *
     * @param  Node\Model $node
     * @return array
     */
    protected function getAsJsTree(Node\Model $node)
    {
        $nodeId = intval($node->getId());
        $name   = $node->getName();
        $isCode = strlen($node->getCode()) > 0;
        if ($isCode) {
            $name = sprintf('[%s] %s', $node->getCode(), $name);
        }
        return array(
            'id'     => $nodeId,
            'parent' => intval($node->getParent()->getId()) ?: '#',
            'text'   => $name,
            'a_attr' => array(
                'href' => $this->getRouter()->generate(
                    'curriculum.read',
                    array('id' => $nodeId, 'format' => '')
                ),
                'data-updateuri' => $this->getRouter()->generate(
                    'curriculum.update:post',
                    array('id' => $nodeId)
                ),
            ),
            'type'   => ($isCode ? 'code' : 'folder'),
        );
    }

    /**
     * Reads a node and outputs it.
     *
     * @param Node\Model $item
     */
    public function read(Node\Model $item)
    {
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum',
                'template' => '@curriculum/read.twig',
                'data'     => array(
                    'item' => $item,
                ),
            )
        );
    }

    /**
     * Saves a form input.
     *
     * @param Node\Model $item
     */
    public function update(Node\Model $item)
    {
        $post = $this->getRequest()->post;
        $code = 200;

        // Perform necessary validations, if any.
        if (isset($post['name'])) {
            $item->setName($post['name']);
        }
        if (isset($post['code'])) {
            $item->setCode($post['code']);
        }
        if (isset($post['reference'])) {
            $item->setReference($post['reference']);
        }
        if (isset($post['description'])) {
            $item->setDescription($post['description']);
        }

        // Save the item
        if (!$this->getNodeMapper()->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        // Consider outputting jsTree node data as well
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item' => $item,
                    ),
                )
            );
        } else {
            // Redirect back to the the last page?
            // Or elsewhere, should validation have failed
            $this->getResponse()->redirect->afterPost(
                $this->getSession()->getHistory()
            );
        }
    }

    /**
     * @return Node\Mapper
     */
    public function getNodeMapper()
    {
        return $this->nodeMapper;
    }

    /**
     * @param  Node\Mapper $nodeMapper
     * @return $this
     */
    public function setNodeMapper($nodeMapper)
    {
        $this->nodeMapper = $nodeMapper;
        return $this;
    }
}
