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
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;

class Controller extends Base
{
    /**
     * @var Node\Mapper
     */
    protected $nodeMapper;

    /**
     * @var LearningArea\Mapper
     */
    protected $areaMapper;

    /**
     * @var LearningLevel\Mapper
     */
    protected $levelMapper;

    /**
     * Controller action for browsing the list of curricula.
     */
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
                    'tree'   => $tree,
                    'areas'  => $this->getAsGroupedList($this->getAreaMapper()->findAllWithSource()),
                    'levels' => $this->getAsGroupedList($this->getLevelMapper()->findAllWithSource()),
                    // Readonly check - for now, check without any parameters
                    'readonly' => !$this->getAuthorizer()->isPathAuthorizedForUser(
                        'curriculum.edit',
                        [],
                        $this->getUser()
                    ),
                ),
            )
        );
    }

    /**
     * JSON search for curriculum nodes
     */
    public function search()
    {
        $search = trim($this->getRequest()->query['query']);
        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data'   => $this->getNodeMapper()->search(
                    $this->getSession()->getAuthUser(),
                    $search
                ),
            )
        );
    }

    /**
     * Gets a list of LearningArea\Model or LearningLevel\Model objects as a
     * list, grouped by source.
     *
     * @param  LearningArea\Model[]|LearningLevel\Model[] $list
     * @return array
     */
    protected function getAsGroupedList($list)
    {
        $returnList = array();
        foreach ($list as $item) {
            $sourceId = $item->getSource()->getId();
            if (!array_key_exists($sourceId, $returnList)) {
                $returnList[$sourceId] = array(
                    'id'   => $sourceId,
                    'name' => $item->getSource()->getName(),
                    'list' => array(),
                );
            }
            $returnList[$sourceId]['list'][] = array(
                'id'    => $item->getId(),
                'name'  => $item->getName(),
            );
        }
        return array_values($returnList);
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
                'data-saveuri' => $this->getRouter()->generate(
                    'curriculum.update:post',
                    array('id' => $nodeId)
                ),
                'data-deleteuri' => $this->getRouter()->generate(
                    'curriculum.delete:post',
                    array('id' => $nodeId)
                ),
            ),
            'li_attr' => array(
                'title' => $name,
            ),
            'type'    => ($isCode ? 'code' : 'folder'),
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
     * Creates a new node
     */
    public function create()
    {
        $item = $this->getNodeMapper()->createModel();

        // Default learning area and level, or a preselected one?

        $post = $this->getRequest()->post;
        $code = 200;

        // Perform necessary validations, if any.
        $item
            ->setName($post['name'])
            ->setCode($post['code'])
            ->setReference($post['reference'])
            ->setDescription($post['description']);

        // set this node's area and level to its parents,
        // if it has parent.
        // (Do this in preSave?)
        if ($post['parent']) {
            $parent = $this->getNodeMapper()->findById($post['parent']);
            $item
                ->setParent($parent)
                ->setLearningArea($parent->getLearningArea())
                ->setLearningLevel($parent->getLearningLevel());
        } else {
            $item
                ->setParent($this->getNodeMapper()->createModel())
                ->setLearningArea($this->getAreaMapper()->findById($post['learningArea']))
                ->setLearningLevel($this->getLevelMapper()->findById($post['learningLevel']));
        }

        // Save the item
        if (!$this->getNodeMapper()->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item' => $item,
                        'tree' => $this->getAsJsTree($item),
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
     * Saves a curriculum.
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

        // Parent: check this node isn't its own ancestor

        // Save the item
        if (!$this->getNodeMapper()->save($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item' => $item,
                        'tree' => $this->getAsJsTree($item),
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
     * Delete action
     *
     * @param Node\Model $item
     */
    public function delete(Node\Model $item)
    {
        $code = 200;
        if (!$this->getNodeMapper()->delete($item)) {
            // Something went wrong, but I know not what
            $code = 500;
        }

        // Send something back
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->setCode($code);
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data'   => array(
                        'item' => $item,
                        'tree' => $this->getAsJsTree($item),
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

    /**
     * @return LearningArea\Mapper
     */
    public function getAreaMapper()
    {
        return $this->areaMapper;
    }

    /**
     * @param  LearningArea\Mapper $areaMapper
     * @return $this
     */
    public function setAreaMapper($areaMapper)
    {
        $this->areaMapper = $areaMapper;
        return $this;
    }

    /**
     * @return LearningLevel\Mapper
     */
    public function getLevelMapper()
    {
        return $this->levelMapper;
    }

    /**
     * @param  LearningLevel\Mapper $levelMapper
     * @return $this
     */
    public function setLevelMapper($levelMapper)
    {
        $this->levelMapper = $levelMapper;
        return $this;
    }
}
