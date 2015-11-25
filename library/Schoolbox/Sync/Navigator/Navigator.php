<?php
/**
 * Navigator
 *
 * Internally collect and remember navigation node data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Navigator;

use \Schoolbox\Collection;

class Navigator {

    /**
     * A collection of Nodes
     *
     * @var Collection
     */
    private $allNodes = null;

    /**
     * Constructor
     *
     * Accepts any number of breadcrumb nodes
     */
    public function __construct() {
        $this->setAllNodes(new Collection());

        $numArgs = func_num_args();

        if ($numArgs) {
            for ($i = 0; $i < $numArgs; $i++) {
                $currentNode = func_get_arg($i);

                /* Only add instances of \Schoolbox\Sync\Navigator\Node
                 */
                if ($currentNode instanceof Node) {
                    $this->getAllNodes()->add($currentNode);
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set all Nodes
     *
     * @param \Schoolbox\Collection $allNodes
     */
    private function setAllNodes($allNodes)
    {
        $this->allNodes = $allNodes;
    }

    /**
     * Get all Nodes
     *
     * @return \Schoolbox\Collection
     */
    public function getAllNodes()
    {
        return $this->allNodes;
    }
}