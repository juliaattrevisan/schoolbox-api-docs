<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Link\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;

/* Link specific
 */
use Schoolbox\Resource\Link;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Renderer extends Base
{

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
                'see_more_block' => ''
            ));

            $this->getPage()->setVar(array(
                'LINK_DISP_COUNT' => $mapperResult->getResult()->count(),
                'LINK_COUNT' => $mapperResult->getCount(),
            ));

            /* phplib
             */
            foreach ($mapperResult->getResult() as $currentLink) {
                $this->getPage()->setVar(array(
                    'LINK_URL' => $currentLink->getHref(),
                    'LINK_TARGET' => $currentLink->getLinkTarget(),
                    'LINK_DESCRIPTION' => \Scrub::text($currentLink->getDescription()),
                    'LINK_COMMENT' => \TextFormatter::cutSentence(\Scrub::text($currentLink->getComment()), 150),
                    'LINK_OWNER' => $currentLink->getOwner()->getId(),
                    'LINK_OWNER_NAME' => $currentLink->getOwner()->formatName(),
                    'LINK_CREATED' => $currentLink->getSubmitted()->getRelativeTime(),
                    'LINK_BREADCRUMB' => $this->spawnBreadcrumbs($currentLink->getFolder()),
                ));

                $this->getPage()->parse('link_block', 'link', true);
            }

            /* "outputMore"
             */
            if ($type == 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            }

            $this->getPage()->parse('RESULT', 'link_t', true);
        } else {
            return;
        }
    }
}
