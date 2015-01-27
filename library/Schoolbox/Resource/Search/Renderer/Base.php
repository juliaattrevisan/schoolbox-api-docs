<?php
/**
 * IRenderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Search\Renderer;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;

abstract class Base {

    /**
     * A sitepage
     *
     * @var null
     */
    protected $page = null;

    /**
     * Constructor
     *
     * @param $page
     */
    public function __construct($page) {
        $this->setPage($page);
    }

    /**
     * Render html fragment
     */
    abstract public function render(MapperResult $collection);

    /* Getters/Setters
     */

    /**
     * @param null $page
     */
    protected function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @return null
     */
    protected function getPage()
    {
        return $this->page;
    }
} 