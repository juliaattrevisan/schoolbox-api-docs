<?php
/**
 * Results
 *
 * A composite class consisting of Searchable-interfaced Mappers
 *
 * The composite also commands each of its minions to render tabs,
 * pagination and search results independently of the other minions
 *
 * search() needs to run before any of the render* methods
 *
 * In the future I'd like to have render(), renderTabs() and
 * renderPagination() running adjacently in twig, but for phplib reasons
 * I have to keep them separate as they are called at different times
 * and I don't want to rock the boat so close to the beach
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

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Search\Renderer as FolderRenderer;

use Schoolbox\Resource\TextboxMapper;
use Schoolbox\Resource\Textbox\Search\Renderer as TextboxRenderer;

use Schoolbox\Resource\File;
use Schoolbox\Resource\File\Search\Renderer as FileRenderer;

class Results implements Searchable
{

    /**
     * Registry of mappers
     *
     * @var Collection
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
     * The thing you searched for
     * @var string
     */
    private $keyword = '';

    /**
     * Constructor
     *
     * $type is 'all' or specific to which tab you selected e.g. 'link'
     * This is used to generate a phplib for "see more" - see outputMore()
     *
     * @param $page
     * @param $type
     */
    public function __construct($page, $type)
    {
        $this->setRegistry(new Collection());
        $this->setType($type);
        $this->setResult(new Collection());

        /* Add searchables (synonymous with values from omaha)
         */
        $this->getRegistry()->add(new Definition('file', new File\Mapper(), new FileRenderer($page)));
        $this->getRegistry()->add(new Definition('folder', new FolderMapper(), new FolderRenderer($page)));
        $this->getRegistry()->add(new Definition('link', new LinkMapper(), new LinkRenderer($page)));
        $this->getRegistry()->add(new Definition('textbox', new TextboxMapper(), new TextboxRenderer($page)));
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
    public function search($user, $keyword, $offset = 0, $limit = null, $sortProperty = '', $sortDirection = 'DESC')
    {
        $result = new Collection();

        /* Run search() on each minion in the registry if 'all' is selected
         * or if this particular minion was chosen specifically (e.g. 'link')
         */
        foreach ($this->getRegistry() as $currentDefinition) {
            if (($this->getType() == 'all') || ($currentDefinition->getType() == $this->getType())) {
                $searcher = $currentDefinition->getMapper()->search($user, $keyword, $offset, $limit, $sortProperty, $sortDirection);

                $result->setOffset($currentDefinition->getType(), $searcher);
            }
        }

        $this->setResult($result);
    }

    /**
     * Render search tabs to chooze from
     */
    public function renderTabs()
    {
        foreach ($this->getRegistry() as $currentDefinition) {
            if (($this->getType() == 'all') || ($currentDefinition->getType() == $this->getType())) {
                $searcher = $this->getResult()->getOffset($currentDefinition->getType());

                $currentDefinition->getRenderer()->renderTab($searcher, $this->getType());
            }
        }
    }

    /**
     * Render search results
     */
    public function render()
    {
        foreach ($this->getRegistry() as $currentDefinition) {
            if (($this->getType() == 'all') || ($currentDefinition->getType() == $this->getType())) {
                $searcher = $this->getResult()->getOffset($currentDefinition->getType());

                $currentDefinition->getRenderer()->render($searcher, $this->getType());
            }
        }
    }

    /**
     * Render pagination
     */
    public function renderPagination($currentPage)
    {

        /* Render pagination from the first $searcher, as they all do it the same
         * and only need to do it once
         */
        foreach ($this->getRegistry() as $currentDefinition) {
            if (($this->getType() == 'all') || ($currentDefinition->getType() == $this->getType())) {
                $searcher = $this->getResult()->getOffset($currentDefinition->getType());

                $currentDefinition->getRenderer()->renderPagination($searcher, $currentPage);

                break;
            }
        }
    }

    /**
     * Count the total number of results for all participants
     * listed in the Registry
     *
     * @return int
     */
    public function countTotalResults()
    {
        $sum = 0;

        foreach ($this->getRegistry() as $currentDefinition) {
            $searcher = $this->getResult()->getOffset($currentDefinition->getType());

            $sum += $searcher->getCount();
        }

        return $sum;
    }

    /* Getters/Setters
     */

    /**
     * Set registry
     *
     * @param Collection|Definition[] $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return Collection|Definition[]
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
     * @param Collection $result
     */
    private function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return Collection
     */
    private function getResult()
    {
        return $this->result;
    }

    /**
     * Set keyword
     *
     * @param string $keyword
     */
    private function setKeyword($keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * Get keyword
     *
     * @return string
     */
    private function getKeyword()
    {
        return $this->keyword;
    }
}
