<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search;

use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource\File;
use Schoolbox\Resource\File\Search\Renderer as FileRenderer;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Search\Renderer as FolderRenderer;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Link\Search\Renderer as LinkRenderer;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Resource\Textbox\Search\Renderer as TextboxRenderer;
use Schoolbox\Forum;
use SitePage;

/**
 * Class Results
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
 * @package Schoolbox\Resource\Search
 */
class Results implements Searchable
{

    /**
     * Minimum number of characters required to initiate search
     */
    public const MIN_SEARCH_LENGTH = 2;


    /**
     * Registry of mappers
     *
     * @var Collection|Definition[]
     */
    private $registry;


    /**
     * Tab that you clicked on
     *
     * e.g. 'all', 'link', 'wiki' etc
     *
     * @var string
     */
    private $type;


    /**
     * Search results
     *
     * @var null
     */
    private $result;


    /**
     * Constructor
     *
     * $type is 'all' or specific to which tab you selected e.g. 'link'
     * This is used to generate a phplib for "see more" - see outputMore()
     *
     * @param SitePage $page
     * @param string|null $type
     */
    public function __construct($page, $type)
    {
        $this->registry = new Collection();
        $this->type = $type;
        $this->result = new Collection();

        /* Add searchables (synonymous with values from omaha)
         */
        $this->registry->add(new Definition('file', File\Mapper::getInstance(), new FileRenderer($page)));
        $this->registry->add(new Definition('folder', FolderMapper::getInstance(), new FolderRenderer($page)));
        $this->registry->add(new Definition('link', LinkMapper::getInstance(), new LinkRenderer($page)));
        $this->registry->add(new Definition('textbox', TextboxMapper::getInstance(), new TextboxRenderer($page)));
        $this->registry->add(new Definition('news', News\Mapper::getInstance(), new News\Search\Renderer($page)));
        $this->registry->add(new Definition('forum', Forum\PostMapper::getInstance(), new Forum\Search\Renderer($page)));
    }


    /**
     * Search using each mapper's custom implementation of Search()
     *
     * @param ArticleResolver $articleResolver
     * @param \Schoolbox\User $user
     * @param string          $keyword
     * @param int             $offset
     * @param null            $limit
     * @param string          $sortProperty
     * @param string          $sortDirection
     * @return void
     */
    public function search(ArticleResolver $articleResolver, $user, $keyword, $offset = 0, $limit = null, $sortProperty = '', $sortDirection = 'DESC'): void
    {
        $result = new Collection();

        /* Single-digit keywords are allowed by User Handler, but not any of the
         * Mappers in this class which all fulfil the MIN_SEARCH_LENGTH requirement
         * (you can't search for single-digit numerics here)
         *
         * This allows $registry to be populated with possibilities, but also causes
         * $result to be an empty collection, returning a null when naively accessed
         * via getOffset() - you have to check that getOffset() returned you something
         * that could return a search result before blindly using it
         */
        if (strlen((string)$keyword) >= self::MIN_SEARCH_LENGTH) {
            /* Run search() on each minion in the registry if 'all' is selected
             * or if this particular minion was chosen specifically (e.g. 'link')
             */
            foreach ($this->registry as $currentDefinition) {
                if (($this->type === 'all') || ($currentDefinition->getType() === $this->type)) {
                    $searcher = $currentDefinition->getMapper()->search($articleResolver, $user, $keyword, $offset, $limit, $sortProperty, $sortDirection);

                    $result->setOffset($currentDefinition->getType(), $searcher);
                }
            }
        }
        $this->result = $result;
    }


    /**
     * Render search tabs to chooze from
     */
    public function renderTabs(): void
    {
        foreach ($this->registry as $currentDefinition) {
            if (($this->type === 'all') || ($currentDefinition->getType() === $this->type)) {
                $searcher = $this->result->getOffset($currentDefinition->getType());

                if (null !== $searcher) {
                    $currentDefinition->getRenderer()->renderTab($searcher);
                }
            }
        }
    }


    /**
     * Render search results
     */
    public function render()
    {
        foreach ($this->registry as $currentDefinition) {
            if (($this->type === 'all') || ($currentDefinition->getType() === $this->type)) {
                $searcher = $this->result->getOffset($currentDefinition->getType());

                if (null !== $searcher) {
                    $currentDefinition->getRenderer()->render($searcher, $this->type);
                }
            }
        }
    }


    /**
     * Render pagination
     *
     * @param int $currentPage
     */
    public function renderPagination(int $currentPage): void
    {

        /* Render pagination from the first $searcher, as they all do it the same
         * and only need to do it once
         */
        foreach ($this->registry as $currentDefinition) {
            if (($this->type === 'all') || ($currentDefinition->getType() === $this->type)) {
                $searcher = $this->result->getOffset($currentDefinition->getType());

                if (null !== $searcher) {
                    $currentDefinition->getRenderer()->renderPagination($searcher, $currentPage);

                    break;
                }
            }
        }
    }


    /**
     * Count the total number of results for all participants
     * listed in the Registry
     *
     * @return int
     */
    public function countTotalResults(): int
    {
        $sum = 0;

        foreach ($this->registry as $currentDefinition) {
            $searcher = $this->result->getOffset($currentDefinition->getType());
            if (!$searcher) {
                continue;
            }
            $sum += $searcher->getCount();
        }

        return $sum;
    }
}
