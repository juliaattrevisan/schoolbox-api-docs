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
    public function __construct() {
        $this->setRegistry(new Collection());

        /* Add searchables
         */
        $this->getRegistry()->add(new LinkMapper());
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
     * @return Collection
     */
    public function search($user, $keyword, $offset = 0, $limit = null, $sortProperty = '', $sortDirection = 'DESC') {
        $result = new Collection();

        foreach($this->getRegistry() as $currentMapper) {
            if ($limit === null) {
                $limit = $currentMapper::DEFAULT_LIMIT;
            }

            $result->add($currentMapper->search($user, $keyword, $offset, $limit, $sortProperty, $sortDirection));
        }

        return $result;
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