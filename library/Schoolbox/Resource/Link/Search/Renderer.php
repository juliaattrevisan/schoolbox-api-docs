<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Link\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;

/* Link specific
 */
use Schoolbox\Resource\Link;
use Schoolbox\Resource\CategoryMapper;

class Renderer extends Base {

    public function renderTab(MapperResult $mapperResult, $type) {
        $this->getPage()->setVar(array(
            'TYPE_COUNT' => $mapperResult->getCount(),
            'TYPE_TITLE' => 'link',
            'TYPE_CLASS' => '',
            'TYPE_NAME' => 'Links'
        ));

        $this->getPage()->setVar(array(
            'RESULT_TITLE' => 'link',
            'RESULT_CLASS' => $type == 'all' ? 'hide loaded' : 'hide',
        ));

        $this->getPage()->setVar(array(
            'RESULT' => ''
        ));

        $this->getPage()->parse('type_block', 'type', true);
        $this->getPage()->parse('result_block', 'result', true);
    }

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function render(MapperResult $mapperResult, $type)
    {
        $this->getPage()->setFile('link_t', 'resource/link/search/results.html');
        $this->getPage()->setBlock('link_t', 'link', 'link_block');
        $this->getPage()->setBlock('link_t', 'see_more', 'see_more_block');

        $this->getPage()->setVar(array(
            'LINK_DISP_COUNT' => $mapperResult->getResult()->count(),
            'LINK_COUNT' => $mapperResult->getCount(),
        ));

        /* phplib
         */
        foreach ($mapperResult->getResult() as $currentLink) {
            $createdTime = new \Alaress_Date_Relative($currentLink->getSubmitted(), 'Y-m-d H:i:s');

            $this->getPage()->setVar(array(
                'LINK_URL' => $currentLink->getHref(),
                'LINK_TARGET' => $currentLink->getTarget(),
                'LINK_DESCRIPTION' => \Scrub::text($currentLink->getDescription()),
                'LINK_COMMENT' => \TextFormatter::cutSentence(\Scrub::text($currentLink->getComment()), 150),
                'LINK_OWNER' => $currentLink->getOwner()->getId(),
                'LINK_OWNER_NAME' => $currentLink->getOwner()->formatName(),
                'LINK_CREATED' => $createdTime->getRelativeTime(),
                'LINK_BREADCRUMB' => $this->spawnBreadcrumbs($currentLink),
            ));

            $this->getPage()->parse('link_block', 'link', true);
        }

        /* "outputMore"
         */
        if ($type == 'all' && $mapperResult->getCount() > \Cfg::get('search_all_limit')) {
            $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
            $this->getPage()->parse('see_more_block', 'see_more');
        }
        else {
            $this->getPage()->setVar('see_more_block', '');
        }

        $this->getPage()->parse('RESULT', 'link_t', true);
    }

    /**
     * Render pagination
     *
     * @param MapperResult $mapperResult
     * @param string $type
     * @param int $currentPage
     */
    public function renderPagination(MapperResult $mapperResult, $type, $currentPage) {
        // clear count
        $this->getPage()->setBlock('RESULT', 'count', 'count_block');
        $this->getPage()->setVar('count_block');

        // render pagination
        $this->getPage()->setBlock('RESULT', 'pagination', 'pagination_block');

        $number_pages = ceil($mapperResult->getCount() / \Cfg::get('search_type_limit'));

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

            // only 1 page to display
        } else {
            $this->getPage()->setVar('pagination_block');
        }

        // render final output
        $this->getPage()->parse(TEMPLATE_body, 'RESULT', true);
        $this->getPage()->outputPage(true);
    }

    /**
     * Breadcrumbs haha
     *
     * @param Link $currentLink
     * @return string
     */
    private function spawnBreadcrumbs(Link $currentLink) {
        $categoryMapper = new CategoryMapper();
        $allCrumbs = $categoryMapper->breadcrumb($currentLink->getFolder());

        $html = array();

        foreach($allCrumbs as $currentCrumb) {
            $html[] = "<a href=\"/homepage/{$currentCrumb->getId()}\">{$currentCrumb->getName()}</a>";
        }

        return implode(' > ', $html);
    }
}