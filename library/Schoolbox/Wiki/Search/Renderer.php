<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Format;

class Renderer extends Handler
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'wiki';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Wikis';

    /**
     * Render
     */
    public function render()
    {
        $categoryStd = new \Category();
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('wiki_t', 'resource/wiki/search/results.html');
            $this->getPage()->setBlock('wiki_t', 'wiki', 'wiki_block');
            $this->getPage()->setVar(array(
                'WIKI_DISP_COUNT' => count($this->getResult($this->getType())),
                'WIKI_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $wiki) {
                foreach ($categoryStd->breadcrumb($wiki['categoryID']) as $i => $breadcrumb) {
                    $wiki['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
                }

                $createdTime = new Alaress_Date_Format($wiki['created'], 'Y-m-d H:i:s');

                $this->getPage()->setVar(array(
                    'WIKI_ID' => $wiki['wikiID'],
                    'WIKI_CID' => $wiki['categoryID'],
                    'WIKI_HEADING' => Scrub::text($wiki['heading']),
                    'WIKI_BODY' => TextFormatter::cutSentence(Scrub::html2text($wiki['body']), 150),
                    'WIKI_OWNER' => $wiki['userID'],
                    'WIKI_OWNER_NAME' => $userHandler->formatName($wiki),
                    'WIKI_CREATED' => $createdTime->getRelativeTime(),
                    'WIKI_BREADCRUMB' => $wiki['breadcrumb'],
                    'WIKI_COUNT' => $this->getCount($this->getType()),
                    'WIKI_SLUG' => $wiki['slug'],
                ));

                $this->getPage()->parse('wiki_block', 'wiki', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'wiki_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
