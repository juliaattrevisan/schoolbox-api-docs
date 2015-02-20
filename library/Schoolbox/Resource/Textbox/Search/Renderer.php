<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Textbox\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;

/* Textbox specific
 */
use Schoolbox\Resource\Textbox;
use Schoolbox\Resource\CategoryMapper;

class Renderer extends Base {

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'textbox';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Textboxes';

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
            $this->getPage()->setFile('textbox_t', 'resource/textbox/search/results.html');
            $this->getPage()->setBlock('textbox_t', 'textbox', 'textbox_block');
            $this->getPage()->setBlock('textbox_t', 'see_more', 'see_more_block');

            $this->getPage()->setVar(array(
                'see_more_block' => ''
            ));

            $this->getPage()->setVar(array(
                'TEXTBOX_DISP_COUNT' => $mapperResult->getResult()->count(),
                'TEXTBOX_COUNT' => $mapperResult->getCount(),
            ));

            /* phplib
             */
            foreach ($mapperResult->getResult() as $currentTextbox) {
                $this->getPage()->setVar(array(
                    'TEXTBOX_HEADING' => \Scrub::text($currentTextbox->getHeading()),
                    'TEXTBOX_BODY' => \TextFormatter::cutSentence(\Scrub::html($currentTextbox->getBody()), 150),
                    'TEXTBOX_BREADCRUMB' => $this->spawnBreadcrumbs($currentTextbox),
                ));

                $this->getPage()->parse('textbox_block', 'textbox', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Cfg::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'textbox_t', true);
        }
        else {
            return;
        }
    }

    /**
     * Breadcrumbs haha
     *
     * @param Textbox $currentTextbox
     * @return string
     */
    private function spawnBreadcrumbs(Textbox $currentTextbox) {
        $categoryMapper = new CategoryMapper();
        $allCrumbs = $categoryMapper->breadcrumb($currentTextbox->getCategory());

        $html = array();

        foreach($allCrumbs as $currentCrumb) {
            $html[] = "<a href=\"/homepage/{$currentCrumb->getId()}\">{$currentCrumb->getName()}</a>";
        }

        $html[] = "<a href=\"/homepage/{$currentTextbox->getCategory()->getId()}\">{$currentTextbox->getCategory()->getName()}</a>";

        return implode(' > ', $html);
    }
}