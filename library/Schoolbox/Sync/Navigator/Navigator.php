<?php
/**
 * Navigator
 *
 * Internally collect and remember navigation node data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Sync\Navigator;

use \Schoolbox\Collection;

class Navigator {

    /**
     * A collection of Nodes
     *
     * @var Collection
     */
    private $allNodes;

    /**
     * Constructor
     *
     * Accepts any number of breadcrumb nodes
     *
     * @param array $nodes
     */
    public function __construct(array $nodes) {
        $this->allNodes = new Collection();

        /* Only add instances of \Schoolbox\Sync\Navigator\Node
         */
        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $this->allNodes->add($node);
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Get all Nodes
     *
     * @return Collection
     */
    public function getAllNodes(): Collection
    {
        return $this->allNodes;
    }
}
