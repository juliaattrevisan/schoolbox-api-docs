<?php
/**
 * IRenderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Search\Renderer;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Scrub;
use SitePage;

abstract class Base
{

    /**
     * A sitepage
     *
     * @var SitePage
     */
    protected $page = null;

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
     * Constructor
     *
     * @param SitePage $page
     */
    public function __construct(SitePage $page)
    {
        $this->setPage($page);
    }

    /**
     * Render clickable tab menu
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function renderTab(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
            $this->getPage()->setVar(array(
                    'TYPE_TYPE' => $this->getType(),
                    'TYPE_NAME' => $this->getTabName(),
                    'TYPE_COUNT' => $mapperResult->getCount(),
                ));

            /* Rage these styles in, because of css specificity
             */
            if ($this->getType() == 'all') {
                $this->getPage()->setVar(array(
                        'TYPE_CLASS' => ''
                    ));
            } elseif (!$mapperResult->getCount()) {
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
    }

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     * @return
     */
    abstract public function render(MapperResult $mapperResult, $type);

    /**
     * Render pagination
     *
     * All minions share the same pagination stuff and is independent
     * of $type - it only needs the $mapperResult counts
     *
     * @param MapperResult $mapperResult
     * @param int $currentPage
     */
    public function renderPagination(MapperResult $mapperResult, $currentPage)
    {
        // clear count
        $this->getPage()->setBlock('RESULT', 'count', 'count_block');
        $this->getPage()->setVar('count_block');

        // render pagination
        $this->getPage()->setBlock('RESULT', 'pagination', 'pagination_block');

        $number_pages = ceil($mapperResult->getCount() / \Config::get('search_type_limit'));

        if ($number_pages > 1) {
            if (($currentPage - 5) <= 1) {
                $start_window = 1;
                $end_window = $number_pages > 10 ? 10 : $number_pages;
            } elseif (($currentPage + 5) >= $number_pages) {
                $start_window = $number_pages - 10;
                $end_window = $number_pages;
            } else {
                $start_window = $currentPage - 5;
                $end_window = $currentPage + 5;
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
                    'PAGE_CLASS' => $i == $currentPage ? 'current' : '',
                ));
                $this->getPage()->parse('pagination_block', 'pagination', true);
            }

            $this->getPage()->setVar(array(
                'PAGE_NAME' => '>',
                'PAGE_NUMBER' => $number_pages,
                'PAGE_CLASS' => '',
            ));

            $this->getPage()->parse('pagination_block', 'pagination', true);
        } // only 1 page to display
        else {
            $this->getPage()->setVar('pagination_block');
        }

        // render final output
        $this->getPage()->parse(TEMPLATE_content, 'RESULT', true);
    }

    /**
     * Breadcrumbs haha
     *
     * @param FolderModel $folder
     * @return string
     */
    protected function spawnBreadcrumbs(FolderModel $folder)
    {
        $categoryMapper = new FolderMapper();
        $allCrumbs = $categoryMapper->breadcrumb($folder);

        $html = array();

        foreach ($allCrumbs as $currentCrumb) {
            $html[] = "<a href=\"/homepage/{$currentCrumb->getId()}\">". Scrub::text($currentCrumb->getName())."</a>";
        }

        $html[] = "<a href=\"/homepage/{$folder->getId()}\">". Scrub::text($folder->getName())."</a>";

        return implode(' > ', $html);
    }

    /* Getters/Setters
     */

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
