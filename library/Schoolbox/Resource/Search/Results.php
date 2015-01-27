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
     * Tab that you clicked on
     *
     * e.g. 'all', 'link', 'wiki' etc
     *
     * @var string
     */
    private $type = '';

    /**
     * Search results
     *
     * @var null
     */
    private $result = null;

    /**
     * Constructor
     *
     * $type is 'all' or specific to which tab you selected e.g. 'link'
     * This is used to generate a phplib for "see more" - see outputMore()
     *
     * @param $page
     * @param $type
     */
    public function __construct($page, $type) {
        $this->setRegistry(new Collection());
        $this->setType($type);
        $this->setResult(new Collection());

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
        $result = new Collection();

        foreach($this->getRegistry() as $currentDefinition) {
            $searcher = $currentDefinition->getMapper()->search($user, $keyword, $offset, $limit, $sortProperty, $sortDirection);

            $result->setOffset(get_class($currentDefinition->getMapper()), $searcher);
        }

        $this->setResult($result);
    }

    /**
     * Render search results
     */
    public function render() {
        foreach($this->getRegistry() as $currentDefinition) {
            $searcher = $this->getResult()->getOffset(get_class($currentDefinition->getMapper()));

            $currentDefinition->getRenderer()->render($searcher, $this->getType());
        }
    }

    /**
     * Count the total number of results for all participants
     * listed in the Registry
     *
     * @return int
     */
    public function countTotalResults() {
        $sum = 0;

        foreach($this->getRegistry() as $currentDefinition) {
            $searcher = $this->getResult()->getOffset(get_class($currentDefinition->getMapper()));

            $sum += $searcher->getCount();
        }

        return $sum;
    }

    /* Getters/Setters
     */

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

    /**
     * Set type
     *
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    private function getType()
    {
        return $this->type;
    }

    /**
     * Set result
     *
     * @param null $result
     */
    private function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return null
     */
    private function getResult()
    {
        return $this->result;
    }
}