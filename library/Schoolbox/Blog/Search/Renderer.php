<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Relative;

class Renderer extends Handler {

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'blog';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Blogs';

    /**
     * Render
     */
    public function render() {
        $categoryStd = new \Category();
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('blog_t', 'resource/blog/search/results.html');
            $this->getPage()->setBlock('blog_t', 'blog', 'blog_block');
            $this->getPage()->setVar(array(
                'BLOG_DISP_COUNT' => count($this->getResult($this->getType())),
                'BLOG_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $blog) {
                foreach ($categoryStd->breadcrumb($blog['cid']) as $i => $breadcrumb) {
                    $blog['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
                }

                $createdTime = new Alaress_Date_Relative($blog['created']);

                $this->getPage()->setVar(array(
                    'BLOG_CID' => $blog['cid'],
                    'BLOG_HEADING' => Scrub::text($blog['heading']),
                    'BLOG_BODY' => TextFormatter::cutSentence(Scrub::html2text($blog['body']), 150),
                    'BLOG_OWNER' => $blog['uid'],
                    'BLOG_OWNER_NAME' => $userHandler->formatName($blog),
                    'BLOG_CREATED' => $createdTime->getRelativeTime(),
                    'BLOG_BREADCRUMB' => $blog['breadcrumb'],
                    'BLOG_COUNT' => $this->getCount($this->getType()),
                ));

                $this->getPage()->parse('blog_block', 'blog', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'blog_t', true);
        }
    }
}