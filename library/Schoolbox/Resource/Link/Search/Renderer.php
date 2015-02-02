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

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'link';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Links';

    /**
     * Render clickable tab menu
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function renderTab(MapperResult $mapperResult, $type) {
        if ($mapperResult->getCount()) {
            $this->getPage()->setVar(array(
                'TYPE_TYPE' => $this->getType(),
                'TYPE_NAME' => $this->getTabName(),
            ));

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
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
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
        else {
            return;
        }
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