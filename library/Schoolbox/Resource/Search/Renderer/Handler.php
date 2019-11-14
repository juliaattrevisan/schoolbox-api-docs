<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search\Renderer;

use SitePage;

abstract class Handler
{

    /**
     * A sitepage
     *
     * @var SitePage
     */
    protected $page = null;

    /**
     * The 2RS nightmare array
     *
     * @var array
     */
    protected $result = array();

    /**
     * The even-more-nightmarish global count array
     *
     * @var array
     */
    protected $count = array();

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = '';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = '';

    /**
     * Current page number
     *
     * @var int
     */
    protected $currentPage = 0;

    /**
     * Make a See More button
     * Used only in 'All' context
     *
     * @var bool
     */
    protected $useSeymourLinks = false;

    /**
     * Constructor
     *
     * @param \SitePage $page
     * @param Base[] $result Equivalent of $result['blog'] etc.
     * @param array $count Equivalent of $count['blog'] etc.
     * @param $currentPage
     */
    public function __construct(\SitePage $page, $result, $count, $currentPage)
    {
        $this->setPage($page);
        $this->setResult($result);
        $this->setCount($count);
        $this->setCurrentPage($currentPage);
        $this->setup();
    }

    /**
     * Setup function called after construction.
     *
     * By default does nothing, but can be used to override the tab name, and
     * anything else which may require localisation.
     */
    protected function setup()
    {
        // noop
    }

    /**
     * Render clickable tab menu
     */
    public function renderTab($count)
    {
        $this->getPage()->setVar(array(
            'TYPE_TYPE' => $this->getType(),
            'TYPE_NAME' => $this->getTabName(),
            'TYPE_COUNT' => $count[$this->getType()],
        ));

        /* Rage these styles in, because of css specificity
         */
        if ($this->getType() == 'all') {
            $this->getPage()->setVar(array(
                'TYPE_CLASS' => ''
            ));
        } elseif (!$count[$this->getType()]) {
            $this->getPage()->setVar(array(
                'TYPE_CLASS' => 'hide'
            ));
        } else {
            $this->getPage()->setVar(array(
                'TYPE_CLASS' => ''
            ));
        }

        $this->getPage()->parse('type_block', 'type', true);

        $this->getPage()->setVar(array(
            'RESULT_TYPE' => $this->getType(),
        ));

        $this->getPage()->parse('result_block', 'result', true);
    }

    /**
     * Get count
     *
     * Augmented getter
     *
     * @param string $type
     * @return int|array
     */
    public function getCount($type = null)
    {
        if ($type !== null) {
            return $this->count[$type];
        }

        return $this->count;
    }

    /**
     * Get result
     *
     * Augmented getter
     *
     * @param string $type
     * @return array
     */
    protected function getResult($type = null)
    {
        if ($type !== null) {
            return $this->result[$type];
        }
        return $this->result;
    }

    /**
     * Render pagination
     *
     * Shared among all the handlers
     */
    public function renderPagination()
    {
        // clear count
        $this->getPage()->setBlock('RESULT', 'count', 'count_block');
        $this->getPage()->setVar('count_block');

        // render pagination
        $this->getPage()->setBlock('RESULT', 'pagination', 'pagination_block');

        $number_pages = ceil($this->getCount($this->getType()) / \Config::get('search_type_limit'));

        if ($number_pages > 1) {
            if (($this->getCurrentPage() - 5) <= 1) {
                $start_window = 1;
                $end_window = $number_pages > 10 ? 10 : $number_pages;
            } elseif (($this->getCurrentPage() + 5) >= $number_pages) {
                $start_window = $number_pages - 10;
                $end_window = $number_pages;
            } else {
                $start_window = $this->getCurrentPage() - 5;
                $end_window = $this->getCurrentPage() + 5;
            }
            //
            // Limit start and end (start can't be < 1, end can't be > num pages)
            $start_window = max($start_window, 1);
            $end_window   = min($end_window, $number_pages);

            $this->getPage()->setVar(array(
                    'PAGE_NAME' => '<',
                    'PAGE_NUMBER' => '1',
                    'PAGE_CLASS' => '',
                ));

            $this->getPage()->parse('pagination_block', 'pagination', true);

            for ($i = $start_window; $i <= $end_window; $i++) {
                $this->getPage()->setVar(array(
                        'PAGE_NAME' => $i,
                        'PAGE_NUMBER' => $i,
                        'PAGE_CLASS' => $i == $this->getCurrentPage() ? 'current' : '',
                    ));

                $this->getPage()->parse('pagination_block', 'pagination', true);
            }

            $this->getPage()->setVar(array(
                    'PAGE_NAME' => '>',
                    'PAGE_NUMBER' => $number_pages,
                    'PAGE_CLASS' => '',
                ));

            $this->getPage()->parse('pagination_block', 'pagination', true);

            // only 1 page to display
        } else {
            $this->getPage()->setVar(array(
                'pagination_block' => ''
            ));
        }
    }

    /**
     * Provide a Seymour link
     *
     * This is optionally disabled. Renderer instantiation is mutable.
     */
    protected function outputSeeMore()
    {
        $this->getPage()->setBlock($this->getType() . '_t', 'see_more', 'see_more_block');

        if ($this->getUseSeymourLinks() && ($this->getCount($this->getType()) > \Config::get('search_all_limit'))) {
            $this->getPage()->setVar(array(
                'MORE_COUNT' => $this->getCount($this->getType())
            ));

            $this->getPage()->parse('see_more_block', 'see_more');
        } else {
            $this->getPage()->setVar('see_more_block', '');
        }
    }

    /* Getters/Setters
     */

    /**
     * Set type
     *
     * @param string $type
     */
    protected function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    protected function getType()
    {
        return $this->type;
    }

    /**
     * Set count
     *
     * @param array $count
     */
    protected function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Set page
     *
     * @param SitePage $page
     */
    protected function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return SitePage
     */
    protected function getPage()
    {
        return $this->page;
    }

    /**
     * Gets the tab the user is currently on, as opposed to the tab these
     * search results are in.
     *
     * @return string
     */
    protected function getCurrentTab()
    {
        // Only the "all" tab has seymour links on.
        if ($this->getUseSeymourLinks()) {
            return 'all';
        }
        return $this->getType();
    }

    /**
     * Set result
     *
     * @param array $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Set current page
     *
     * @param int $currentPage
     */
    protected function setCurrentPage($currentPage)
    {
        $this->currentPage = $currentPage;
    }

    /**
     * Get current page
     *
     * @return int
     */
    protected function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Set use seymour links
     *
     * @param boolean $useSeymourLinks
     */
    public function setUseSeymourLinks($useSeymourLinks)
    {
        $this->useSeymourLinks = $useSeymourLinks;
    }

    /**
     * Get use seymour links
     *
     * @return boolean
     */
    public function getUseSeymourLinks()
    {
        return $this->useSeymourLinks;
    }

    /**
     * Set tab name
     *
     * @param string $tabName
     */
    protected function setTabName($tabName)
    {
        $this->tabName = $tabName;
    }

    /**
     * Get tab name
     *
     * @return string
     */
    protected function getTabName()
    {
        return $this->tabName;
    }
}
