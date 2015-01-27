<?php
/**
 * Results
 *
 * A composite class consisting of Searchable-interfaced Mappers
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Search;

use Schoolbox\Mapper\Searchable;
use Schoolbox\Collection;

use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Link\Search\Renderer as LinkRenderer;


class Results implements Searchable {

    /**
     * Registry of mappers
     *
     * @var \Schoolbox\Collection
     */
    private $registry = null;

    /**
     * Constructor
     */
    public function __construct($page) {
        $this->setRegistry(new Collection());

        /* Add searchables
         */
        $this->getRegistry()->add(new Definition(new LinkMapper(), new LinkRenderer($page)));
    }

    /**
     * Search using each mapper's custom implementation of Search()
     *
     * @param \Schoolbox\User $user
     * @param string $keyword
     * @param int $offset
     * @param null $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return void
     */
    public function search($user, $keyword, $offset = 0, $limit = null, $sortProperty = '', $sortDirection = 'DESC') {
        foreach($this->getRegistry() as $currentDefinition) {
            $results = $currentDefinition->getMapper()->search($user, $keyword, $offset, $limit, $sortProperty, $sortDirection);

            $currentDefinition->getRenderer()->render($results);
        }

        return;
    }

    /**
     * Set registry
     *
     * @param null $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return null
     */
    private function getRegistry()
    {
        return $this->registry;
    }
}