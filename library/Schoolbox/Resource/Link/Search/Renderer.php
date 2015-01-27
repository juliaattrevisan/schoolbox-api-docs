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

class Renderer extends Base {

    /**
     * Render html fragment
     */
    public function render(MapperResult $mapperResult)
    {
        $this->getPage()->setFile('link_t', 'resource/link/search/results.html');
        $this->getPage()->setBlock('link_t', 'link', 'link_block');

        $this->getPage()->setVar(array(
            'LINK_DISP_COUNT' => $mapperResult->getResult()->count(),
            'LINK_COUNT' => $mapperResult->getCount(),
        ));

        foreach ($mapperResult->getResult() as $currentLink) {
//            foreach ($categoryStd->breadcrumb($link['category']) as $i => $breadcrumb) {
//                $link['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
//            }
//
//
            $createdTime = new \Alaress_Date_Relative($currentLink->getSubmitted(), 'Y-m-d H:i:s');

            $this->getPage()->setVar(array(
                'LINK_URL' => $currentLink->getHref(),
                'LINK_ICON' => '',
                'LINK_TARGET' => $currentLink->getTarget(),
                'LINK_DESCRIPTION' => \Scrub::text($currentLink->getDescription()),
                'LINK_COMMENT' => \TextFormatter::cutSentence(\Scrub::text($currentLink->getComment()), 150),
                'LINK_OWNER' => $currentLink->getOwner()->getId(),
                'LINK_OWNER_NAME' => $currentLink->getOwner()->formatName(),
                'LINK_CREATED' => $createdTime->getRelativeTime(),
//                'LINK_BREADCRUMB' => $link['breadcrumb'],
//                'LINK_COUNT' => $mapperResult->getCount(),
            ));

            $this->getPage()->parse('link_block', 'link', true);
        }
    }
}