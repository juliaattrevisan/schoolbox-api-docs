<?php
/**
 * Definition
 *
 * A container for a Mapper and a Search Results renderer
 *
 * Can do away with all that once everything converts to twig
 * but until then, I'm just going to flow with the status quo
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Search;

use Schoolbox\Mapper;

class Definition {

    /**
     * Mapper instance
     *
     * @var null
     */
    protected $mapper = null;

    /**
     * Renderer instance
     *
     * @var null
     */
    protected $renderer = null;

    /**
     * A collection of found items
     *
     * @var null
     */
    protected $results = null;

    /**
     * Constructor
     */
    public function __construct(Mapper $mapper, Renderer\Base $renderer) {
        $this->setMapper($mapper);
        $this->setRenderer($renderer);
    }

    /* Getters/Setters
     */

    /**
     * Set mapper
     *
     * @param null $mapper
     */
    protected function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Get mapper
     *
     * @return null
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * Set renderer
     *
     * @param null $renderer
     */
    protected function setRenderer($renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Get renderer
     *
     * @return null
     */
    public function getRenderer()
    {
        return $this->renderer;
    }
}