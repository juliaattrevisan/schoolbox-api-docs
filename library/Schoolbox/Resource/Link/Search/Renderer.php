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

        new \Alaress_Probe($mapperResult);
        $this->getPage()->setFile('link_t', 'resource/link/search/results.html');
        $this->getPage()->setBlock('link_t', 'link', 'link_block');
//        $this->getPage()->setVar(array(
//            'LINK_DISP_COUNT' => count($result['link']),
//            'LINK_COUNT' => $count['link'],
//        ));
//
//
//        foreach ($result['link'] as $link) {
//            foreach ($categoryStd->breadcrumb($link['category']) as $i => $breadcrumb) {
//                $link['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
//            }
//
//            $url = new Url($link['url']);
//
//            if ($url->isLocalUrl()) {
//                $linkUrl = $url->getRelativeURL();
//                $linkTarget = '';
//            } else {
//                $linkUrl = $url->getBase64EncodedRedirect();
//                $linkTarget = 'external';
//            }
//
//            $userHandler = new User();
//            $createdTime = new Alaress_Date_Relative($link['submitted']);
//
//            $this->getPage()->setVar(array(
//                    'LINK_URL' => $linkUrl,
//                    'LINK_ICON' => $link['iconFile'],
//                    'LINK_TARGET' => $linkTarget,
//                    'LINK_DESCRIPTION' => Scrub::text($link['description']),
//                    'LINK_COMMENT' => TextFormatter::cutSentence(Scrub::text($link['comment']), 150),
//                    'LINK_OWNER' => $link['uid'],
//                    'LINK_OWNER_NAME' => $userHandler->formatName($link),
//                    'LINK_CREATED' => $createdTime->getRelativeTime(),
//                    'LINK_BREADCRUMB' => $link['breadcrumb'],
//                    'LINK_COUNT' => $count['link'],
//                ));
//
//            $this->getPage()->parse('link_block', 'link', true);
//        }
    }
}