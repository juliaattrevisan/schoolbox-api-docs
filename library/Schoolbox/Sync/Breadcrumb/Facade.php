<?php
/**
 * Facade
 *
 * Internally collect and remember breadcrumb node data
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Breadcrumb;

use \Schoolbox\Collection;

class Facade {

    /**
     * A collection of Breadcrumb Nodes
     *
     * @var Collection
     */
    private $allBreadcrumbs = null;

    /**
     * Constructor
     *
     * Accepts any number of breadcrumb nodes
     */
    public function __construct() {
        $this->setAllBreadcrumbs(new Collection());

        $numArgs = func_num_args();

        if ($numArgs) {
            for ($i = 0; $i < $numArgs; $i++) {
                $currentNode = func_get_arg($i);

                /* Only add instances of \Schoolbox\Sync\Breadcrumb\Node
                 */
                if ($currentNode instanceof Node) {
                    $this->getAllBreadcrumbs()->add($currentNode);
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set all breadcrumbs
     *
     * @param \Schoolbox\Collection $allBreadcrumbs
     */
    private function setAllBreadcrumbs($allBreadcrumbs)
    {
        $this->allBreadcrumbs = $allBreadcrumbs;
    }

    /**
     * Get all breadcrumbs
     *
     * @return \Schoolbox\Collection
     */
    public function getAllBreadcrumbs()
    {
        return $this->allBreadcrumbs;
    }
}