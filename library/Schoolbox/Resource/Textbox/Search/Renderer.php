<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;

/* Textbox specific
 */
use Schoolbox\Resource\Textbox;

class Renderer extends Base
{

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
                    'TEXTBOX_ID' => $currentTextbox->getId(),
                    'TEXTBOX_FOLDER_ID' => $currentTextbox->getCategory()->getId(),
                    'TEXTBOX_HEADING' => \Scrub::text($currentTextbox->getHeading()),
                    'TEXTBOX_BODY' => \TextFormatter::cutSentence(\Scrub::html($currentTextbox->getBody()), 150),
                    'TEXTBOX_BREADCRUMB' => $this->spawnBreadcrumbs($currentTextbox->getCategory()),
                ));

                $this->getPage()->parse('textbox_block', 'textbox', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'textbox_t', true);
        } else {
            return;
        }
    }
}
