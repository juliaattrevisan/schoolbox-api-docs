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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search;

use Schoolbox\Mapper;
use Schoolbox\Mapper\Searchable;

class Definition
{

    /**
     * The type of item for this search definition
     * e.g. 'link', 'wiki', 'forum' ...
     */
    private $type = '';

    /**
     * Mapper instance
     *
     * @var Mapper
     */
    private $mapper = null;

    /**
     * Renderer instance
     *
     * @var Renderer\Base
     */
    private $renderer = null;


    /**
     * @param string        $type
     * @param Searchable    $mapper
     * @param Renderer\Base $renderer
     */
    public function __construct($type, Searchable $mapper, Renderer\Base $renderer)
    {
        $this->setType($type);
        $this->setMapper($mapper);
        $this->setRenderer($renderer);
    }

    /* Getters/Setters
     */

    /**
     * Set mapper
     *
     * @param Searchable $mapper
     */
    private function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Get mapper
     *
     * @return Searchable
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * Set renderer
     *
     * @param Renderer\Base $renderer
     */
    private function setRenderer($renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Get renderer
     *
     * @return Renderer\Base
     */
    public function getRenderer()
    {
        return $this->renderer;
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
    public function getType()
    {
        return $this->type;
    }
}
