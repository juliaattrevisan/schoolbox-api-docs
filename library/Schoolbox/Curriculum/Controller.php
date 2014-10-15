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
            $nodeId = intval($node->getId());
            $name   = $node->getName();
            $isCode = strlen($node->getCode()) > 0;
            if ($isCode) {
                $name = sprintf('[%s] %s', $node->getCode(), $name);
            }
            $tree[] = array(
                'id'     => $nodeId,
                'parent' => intval($node->getParent()->getId()) ?: '#',
                'text'   => $name,
                'a_attr' => array(
                    'href' => $this->getRouter()->generate(
                        'curriculum.read',
                        array('id' => $nodeId, 'format' => '')
                    ),
                    'data-edituri' => $this->getRouter()->generate(
                        'curriculum.edit',
                        array('id' => $nodeId, 'format' => '')
                    ),
                ),
                'type'   => ($isCode ? 'code' : 'folder'),
            );
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

    public function read($id)
    {
        $node = $this->getNodeMapper()->findById($id);
        // Load parent nodes and all of their subnodes, and dump them in a tree
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum',
                'template' => '@curriculum/read.twig',
                'data'     => array(
                    'item' => $node,
                ),
            )
        );
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
