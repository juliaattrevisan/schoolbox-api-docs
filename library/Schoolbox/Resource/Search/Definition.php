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
     * The type of item for this search definition
     * e.g. 'link', 'wiki', 'forum' ...
     */
    private $type = '';

    /**
     * Mapper instance
     *
     * @var null
     */
    private $mapper = null;

    /**
     * Renderer instance
     *
     * @var null
     */
    private $renderer = null;

    /**
     * Constructor
     */
    public function __construct($type, Mapper $mapper, Renderer\Base $renderer) {
        $this->setType($type);
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
    private function setMapper($mapper)
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
    private function setRenderer($renderer)
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

    /**
     * Set type
     *
     * @param mixed $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }
}