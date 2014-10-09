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
        $nodeList = $this->getNodeMapper()->getCollectionAsTree(
            $this->getNodeMapper()->findAll()
        );
        // Load parent nodes and all of their subnodes, and dump them in a tree
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum',
                'template' => '@curriculum/browse.twig',
                'data'     => array(
                    'itemList' => $nodeList,
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
